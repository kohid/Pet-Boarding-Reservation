<?php
/**
 * ElementorTags: the Pawfectly Event group and one Dynamic Tag per Add Event field, on small
 * stand-ins for Elementor's tag base classes and manager (Elementor itself isn't loaded here).
 */

namespace Elementor\Core\DynamicTags {
	abstract class Base_Tag {
		private $controls = array();
		private $settings;
		public function __construct( array $data = array() ) {
			$this->settings = $data['settings'] ?? array();
			$this->register_controls();
		}
		protected function register_controls() {}
		public function add_control( $id, array $args ) { $this->controls[ $id ] = $args; }
		public function get_controls() { return $this->controls; }
		public function get_settings( $key = null ) {
			$all = $this->settings;
			foreach ( $this->controls as $id => $c ) {
				$all[ $id ] = $all[ $id ] ?? ( $c['default'] ?? '' );
			}
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
	}
	abstract class Tag extends Base_Tag {
		abstract public function render();
	}
	abstract class Data_Tag extends Base_Tag {
		abstract protected function get_value( array $options = array() );
	}
}
namespace Elementor\Modules\DynamicTags {
	class Module {
		const TEXT_CATEGORY  = 'text';
		const URL_CATEGORY   = 'url';
		const IMAGE_CATEGORY = 'image';
		const COLOR_CATEGORY = 'color';
	}
}
namespace Elementor {
	class Controls_Manager {
		const TEXT   = 'text';
		const SELECT = 'select';
		const MEDIA  = 'media';
	}
}
namespace {
	require __DIR__ . '/bootstrap.php';
	require __DIR__ . '/post-stubs.php';
	require __DIR__ . '/user-stubs.php';
	function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
	function get_the_ID() { return $GLOBALS['paw_the_id']; }
	function is_singular( $t = '' ) { return true; }
	function wp_enqueue_style( ...$a ) {}
	function wp_enqueue_script( ...$a ) {}

	class Fake_Tags_Manager {
		public $groups = array();
		public $tags   = array();
		public function register_group( $name, array $settings ) { $this->groups[ $name ] = $settings; }
		public function register( $tag ) { $this->tags[ $tag->get_name() ] = $tag; }
	}

	$none = new Fake_Tags_Manager();
	Pawfectly\ElementorTags::register( $none );
	t( 'without Elementor Pro nothing registers', array() === $none->tags && array() === $none->groups );

	eval( 'namespace ElementorPro; class Plugin {}' ); // Elementor Pro is active from here on.
	$m = new Fake_Tags_Manager();
	Pawfectly\ElementorTags::register( $m );
	t( 'the Pawfectly Event group', array( 'title' => 'Pawfectly Event' ) === ( $m->groups['pawfectly-event'] ?? null ) );
	$cats = array();
	foreach ( $m->tags as $name => $tag ) {
		$cats[ $tag->get_categories()[0] ][] = $name;
	}
	t( 'a text tag for every text field, plus section rows and the formatted description', count( Pawfectly\EventFields::TEXT ) + 2 === count( $cats['text'] ?? array() ) && isset( $m->tags['paw-event-section-rows'], $m->tags['paw-event-description-html'] ) );
	t( 'a link tag for every link', count( Pawfectly\EventFields::LINKS ) === count( $cats['url'] ?? array() ) );
	t( 'the cover photo and the category colour', array( 'paw-event-photo' ) === ( $cats['image'] ?? null ) && array( 'paw-event-color' ) === ( $cats['color'] ?? null ) );
	$every = array();
	foreach ( $m->tags as $tag ) {
		$every[] = $tag->get_group() . '|' . ( '' !== $tag->get_title() ? 'titled' : 'untitled' );
	}
	t( 'every tag is in the group and has a title', array( 'pawfectly-event|titled' ) === array_values( array_unique( $every ) ) );
	t( 'no member code tag', ! array_filter( array_keys( $m->tags ), static fn( $n ) => str_contains( $n, 'coupon' ) ) );

	// Values, on the X-Mas Party.
	$ev = Pawfectly\Events::save( Pawfectly\Events::sanitize( array(
		'title' => 'X-Mas Party', 'start_date' => '2026-12-19', 'start_time' => '10:00', 'end_date' => '2026-12-19', 'end_time' => '14:00',
		'venue' => 'Paw-radise', 'price' => '15', 'photo_id' => '7', 'status' => 'publish', 'coupon' => 'PawPark', 'description' => '<p>Deck the <strong>paws</strong>!</p>',
		'sections' => array( 'includes' => array( 'heading' => 'Everything Your {price} Ticket Includes', 'rows' => array( array( 'emoji' => '📸', 'text' => 'Photo booth' ) ) ), 'sessions' => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'S1', 'who' => '' ) ) ) ),
	) ) );
	$GLOBALS['paw_the_id'] = $ev;
	$out = static function ( string $name, array $settings = array() ) use ( $m ): string {
		$class = get_class( $m->tags[ $name ] );
		$tag   = new $class( array( 'settings' => $settings ) ); // Elementor makes a new tag by class name, with the saved settings.
		ob_start();
		$tag->render();
		return (string) ob_get_clean();
	};
	$value = static function ( string $name ) use ( $m ) {
		$class = get_class( $m->tags[ $name ] );
		return ( new $class() )->get_value();
	};
	t( 'a text tag prints its value', 'X-Mas Party' === $out( 'paw-event-title' ) );
	t( 'text is escaped', '' === $out( 'paw-event-tagline' ) && 'Paw-radise' === $out( 'paw-event-venue' ) );
	t( 'date and time formats: the default, then your own', 'Saturday, December 19th, 2026' === $out( 'paw-event-start-date' ) && 'Dec 19' === $out( 'paw-event-start-date', array( 'format' => 'M j' ) ) && '2:00 PM' === $out( 'paw-event-end-time' ) );
	t( 'section heading: pick the section', 'Everything Your $15 Ticket Includes' === $out( 'paw-event-section-heading', array( 'section' => 'includes' ) ) );
	t( 'the section control lists every section', array( 'sessions', 'includes', 'goodies', 'good_to_know', 'closing' ) === array_keys( ( new ( get_class( $m->tags['paw-event-section-heading'] ) )() )->get_controls()['section']['options'] ) );
	$rows = $out( 'paw-event-section-rows', array( 'section' => 'sessions' ) );
	t( 'section rows: the repeater as a list, pick the section', str_contains( $rows, '<ul class="paw-ev-list paw-ev-list--sessions">' ) && str_contains( $rows, '<span class="paw-ev-row__name">S1</span>' ) );
	t( 'section rows: another section', str_contains( $out( 'paw-event-section-rows', array( 'section' => 'includes' ) ), '<span class="paw-ev-row__text">Photo booth</span>' ) );
	t( 'section rows: the section control lists the four repeaters', array( 'sessions', 'includes', 'goodies', 'good_to_know' ) === array_keys( ( new ( get_class( $m->tags['paw-event-section-rows'] ) )() )->get_controls()['section']['options'] ) );
	t( 'section rows: only the four repeaters, never another part', '' === $out( 'paw-event-section-rows', array( 'section' => 'signup' ) ) && '' === $out( 'paw-event-section-rows', array( 'section' => 'description' ) ) );
	t( 'section rows: an empty section prints nothing on the site', '' === $out( 'paw-event-section-rows', array( 'section' => 'goodies' ) ) );
	t( 'description (formatted): keeps its HTML', str_contains( $out( 'paw-event-description-html' ), '<strong>paws</strong>' ) );
	t( 'spots left: pick the session', '' === $out( 'paw-event-spots-left', array( 'session' => '1' ) ) );
	t( 'a link tag', str_ends_with( $value( 'paw-event-tickets' ), '/event/x-mas-party/#paw-event-reserve' ) && str_contains( $value( 'paw-event-google' ), 'calendar.google.com' ) );
	t( 'the image tag', array( 'id' => 7, 'url' => 'https://example.test/logo-7.png' ) === $value( 'paw-event-photo' ) );
	t( 'the colour tag', '#ff7f50' === $value( 'paw-event-color' ) );
	$page                  = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Home', 'post_status' => 'publish' ) );
	$GLOBALS['paw_the_id'] = $page;
	$photo = static function ( array $settings ) use ( $m ) {
		$class = get_class( $m->tags['paw-event-photo'] );
		return ( new $class( array( 'settings' => $settings ) ) )->get_value();
	};
	$fallback = array( 'id' => 9, 'url' => 'https://example.test/fallback.png' );
	$GLOBALS['paw_the_id'] = $ev;
	t( 'the cover photo has a Fallback image setting, used only without a photo', 'media' === ( ( new ( get_class( $m->tags['paw-event-photo'] ) )() )->get_controls()['fallback']['type'] ?? '' ) && array( 'id' => 7, 'url' => 'https://example.test/logo-7.png' ) === $photo( array( 'fallback' => $fallback ) ) );
	$GLOBALS['paw_the_id'] = $page;
	t( 'not on an event: empty, so Elementor\'s fallback shows', '' === $out( 'paw-event-title' ) && '' === $value( 'paw-event-tickets' ) && array() === $value( 'paw-event-photo' ) && '' === $value( 'paw-event-color' ) );
	t( 'no cover photo: the Fallback image', $fallback === $photo( array( 'fallback' => $fallback ) ) );

	done();
}

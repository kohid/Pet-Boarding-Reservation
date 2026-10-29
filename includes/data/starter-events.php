<?php
/**
 * The starter events (StarterEvents): 30 real-looking Pawfectly events, September 2026 to
 * January 2027. Each is an Events::sanitize() input, with 'category' as a category name
 * (StarterEvents turns it into the slug) and times in the site's timezone.
 *
 * @package Pawfectly
 */

defined( 'ABSPATH' ) || exit;

$park   = array( 'venue' => 'Paw-radise Indoor Dog Park', 'address' => 'Estero, FL' );
$phone  = '239-420-7418';
$member = '🐾 FREE for Dog Park Members — call or text ' . $phone . ' for your coupon code';
$vax    = array( 'emoji' => '💉', 'text' => 'Current Rabies, Bordetella and DHPP vaccinations are required (photos can be texted to ' . $phone . ').' );
$bite   = array( 'emoji' => '🐾', 'text' => 'Dogs must be socialized and good around other pets — the 1 Bite Policy applies.' );
$leash  = array( 'emoji' => '🦮', 'text' => 'Please keep dogs leashed in the parking lot and lobby.' );
$water  = array( 'emoji' => '💧', 'text' => 'Fresh water and shaded rest areas are available all day.' );
$humans = array( 'emoji' => '🧑', 'text' => 'This one is for humans — please leave your pups at home.' );
$sizes  = static fn( string $a, string $b, string $c ): array => array(
	'rows' => array(
		array( 'from' => $a, 'to' => $b, 'name' => 'Session 1', 'who' => 'Small dogs (under 30 lbs)' ),
		array( 'from' => $b, 'to' => $c, 'name' => 'Session 2', 'who' => 'Large dogs (30+ lbs)' ),
	),
);
$one    = static fn( string $from, string $to, string $who ): array => array( 'rows' => array( array( 'from' => $from, 'to' => $to, 'name' => '', 'who' => $who ) ) );
// Rows as array( emoji, text ) pairs, or the shared rows above as they are.
$rows   = static fn( array ...$pairs ): array => array( 'rows' => array_map( static fn( array $p ): array => isset( $p['text'] ) ? $p : array( 'emoji' => $p[0], 'text' => $p[1] ), $pairs ) );
$gifts  = static fn( array ...$sets ): array => array( 'rows' => array_map( static fn( array $s ): array => array( 'emoji' => $s[0], 'title' => $s[1], 'text' => $s[2] ), $sets ) );

$puppy_social = static fn( string $day ): array => $park + array(
	'title' => 'Puppy Social Hour', 'emoji' => '🐶', 'category' => 'Social', 'start' => "$day 18:00", 'end' => "$day 19:00", 'price' => '12', 'spots' => '12',
	'tagline' => 'Safe, supervised play for puppies 10 weeks to 6 months',
	'description' => '<p>Early, positive play is the best start a puppy can get. Our trainers run a calm, supervised hour where pups learn to meet new friends, take breaks and come back to you when called.</p><p>Bring treats, questions and your camera — puppy zoomies are guaranteed.</p>',
	'sections' => array(
		'sessions' => $one( '18:00', '19:00', 'Puppies 10 weeks – 6 months' ),
		'includes' => $rows( array( '🐾', 'One hour of supervised puppy play' ), array( '🎓', 'Tips from a certified trainer' ), array( '🍪', 'Puppy-safe treat samples' ) ),
		'good_to_know' => $rows( array( '💉', 'Puppies need at least their first two rounds of DHPP vaccinations.' ), $leash, array( '🐾', 'Small and shy pups get their own corner to warm up.' ) ),
	),
);
$adoption = static fn( string $day ): array => $park + array(
	'title' => 'Adoption Day', 'emoji' => '🏡', 'category' => 'Adoption', 'start' => "$day 10:00", 'end' => "$day 15:00", 'free' => true,
	'tagline' => 'Meet adoptable dogs from our local rescue partners',
	'description' => '<p>Our local rescue partners bring adoptable dogs of every size and age to Paw-radise for the day. Meet them in a relaxed setting, talk with foster families, and maybe find your new best friend.</p><p>Already have a dog? Bring them along for a supervised meet-and-greet before you adopt.</p>',
	'sections' => array(
		'includes' => $rows( array( '🐕', 'Meet-and-greets with adoptable dogs' ), array( '📋', 'On-site adoption applications' ), array( '🤝', 'Chat with foster families about each dog' ) ),
		'goodies' => $gifts( array( '🎁', 'New-Parent Bag', 'Every family that adopts goes home with a leash, food samples and a free first bath.' ) ),
		'good_to_know' => $rows( array( '🏡', 'Adoption fees and approvals are set by each rescue.' ), array( '🐾', 'Bring your current dog for a meet-and-greet — vaccination records required.' ), $leash ),
	),
);
$playgroup = static fn( string $day ): array => $park + array(
	'title' => 'Small-Dog Playgroup', 'emoji' => '🐩', 'category' => 'Social', 'start' => "$day 18:00", 'end' => "$day 19:00", 'price' => '10', 'spots' => '15', 'coupon' => 'PAWPARK', 'note' => $member,
	'tagline' => 'An hour of play just for dogs under 30 lbs',
	'description' => '<p>Little dogs, big fun. The whole park is reserved for small breeds, with low ramps, tunnels and a staff member on the floor the entire hour.</p>',
	'sections' => array(
		'sessions' => $one( '18:00', '19:00', 'Small dogs (under 30 lbs)' ),
		'includes' => $rows( array( '🐾', 'Off-leash play in the whole park' ), array( '🧑‍🏫', 'A play attendant on the floor' ), array( '🦴', 'Small-breed treat samples' ) ),
		'good_to_know' => array( 'rows' => array( $vax, $bite, $water ) ),
	),
);
$obedience = static fn( string $day, string $topic, string $text ): array => $park + array(
	'title' => "Basic Obedience Class: $topic", 'emoji' => '🎓', 'category' => 'Training', 'start' => "$day 18:30", 'end' => "$day 19:30", 'price' => '25', 'spots' => '8',
	'tagline' => 'A small-group class with a certified trainer',
	'description' => "<p>$text</p><p>Classes are kept to eight dogs so everyone gets hands-on help. Bring a hungry pup, a 6-foot leash and plenty of small treats.</p>",
	'sections' => array(
		'sessions' => $one( '18:30', '19:30', 'Dogs 6 months and older' ),
		'includes' => $rows( array( '🎓', 'One hour with a certified trainer' ), array( '📄', 'A take-home practice sheet' ), array( '🦴', 'Training treats' ) ),
		'good_to_know' => $rows( $vax, array( '🦮', 'Please use a flat collar or harness and a 6-foot leash (no retractable leashes).' ), array( '⏰', 'Arrive 10 minutes early so your dog can settle in.' ) ),
	),
);

return array(
	$park + array(
		'title' => 'Fall Kickoff Pawty', 'emoji' => '🍂', 'category' => 'Social', 'start' => '2026-09-12 10:00', 'end' => '2026-09-12 14:00', 'price' => '10', 'spots' => '30', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'Say goodbye to summer with a morning of off-leash fun',
		'description' => '<p>Fall is finally here! Join us for a cool-down celebration with indoor play, a pup-friendly snack bar and a photo corner decked out in autumn leaves.</p>',
		'sections' => array(
			'sessions' => $sizes( '10:00', '12:00', '14:00' ),
			'includes' => $rows( array( '🐾', 'Two hours of off-leash play' ), array( '📸', 'Autumn photo corner' ), array( '🍪', 'Pumpkin pup-cake samples' ), array( '🎁', 'A goodie bag for every dog' ) ),
			'goodies' => $gifts( array( '🍁', 'Leaf Pile Dive', 'A giant (dog-safe) pile of fabric leaves to jump into — bring your camera.' ) ),
			'good_to_know' => array( 'rows' => array( $vax, $bite, $water ) ),
		),
	),
	$puppy_social( '2026-09-16' ),
	$adoption( '2026-09-19' ),
	$obedience( '2026-09-24', 'Sit, Stay & Come', 'Build the three cues every dog needs: a reliable sit, a calm stay and a recall you can count on.' ),
	$park + array(
		'title' => 'Yappy Hour', 'emoji' => '🍹', 'category' => 'Social', 'start' => '2026-09-26 17:00', 'end' => '2026-09-26 19:00', 'price' => '8', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'Mocktails for you, pup-sicles for them',
		'description' => '<p>Wind down the week with your best friend. Dogs play while their humans enjoy mocktails, lawn games and good company.</p>',
		'sections' => array(
			'includes' => $rows( array( '🍹', 'A mocktail for every human' ), array( '🧊', 'Frozen pup-sicles for the dogs' ), array( '🎶', 'Live acoustic music' ) ),
			'good_to_know' => array( 'rows' => array( $vax, $bite, $leash ) ),
		),
	),
	$park + array(
		'title' => 'Agility Intro Workshop', 'emoji' => '🥏', 'category' => 'Training', 'start' => '2026-10-03 09:00', 'end' => '2026-10-03 11:00', 'price' => '30', 'spots' => '10',
		'tagline' => 'Tunnels, jumps and weave poles for beginners',
		'description' => '<p>Curious about agility? Our trainers walk you and your dog through every obstacle at beginner height, with plenty of praise and treats along the way.</p>',
		'sections' => array(
			'sessions' => array(
				'rows' => array(
					array( 'from' => '09:00', 'to' => '10:00', 'name' => 'Group A', 'who' => 'Dogs new to agility' ),
					array( 'from' => '10:00', 'to' => '11:00', 'name' => 'Group B', 'who' => 'Dogs who have tried a few obstacles' ),
				),
			),
			'includes' => $rows( array( '🥏', 'A one-hour guided course run' ), array( '🎓', 'Coaching from an agility trainer' ), array( '📸', 'Action photos of your dog' ) ),
			'good_to_know' => $rows( $vax, array( '🩺', 'Dogs should be at least 1 year old and in good health.' ), array( '👟', 'Wear comfortable shoes — you will be running too!' ) ),
		),
	),
	$playgroup( '2026-10-07' ),
	$park + array(
		'title' => 'Grooming Pop-Up: Nails & Teeth', 'emoji' => '✂️', 'category' => 'Grooming', 'start' => '2026-10-10 10:00', 'end' => '2026-10-10 14:00', 'price' => '15', 'spots' => '20', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'Quick nail trims and teeth brushing, no appointment needed',
		'description' => '<p>Our groomers set up at Paw-radise for a day of quick touch-ups. Walk in, pick a time slot and your pup will be done in about 15 minutes.</p>',
		'sections' => array(
			'sessions' => array(
				'rows' => array(
					array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Morning', 'who' => 'All dogs' ),
					array( 'from' => '12:00', 'to' => '14:00', 'name' => 'Afternoon', 'who' => 'All dogs' ),
				),
			),
			'includes' => $rows( array( '✂️', 'Nail trim and file' ), array( '🪥', 'Teeth brushing with enzyme paste' ), array( '👂', 'Ear check and clean' ) ),
			'good_to_know' => $rows( $vax, array( '😟', 'Nervous dogs are welcome — tell us and we will take it slow.' ) ),
		),
	),
	$park + array(
		'title' => 'Paws & Pints Fundraiser', 'emoji' => '🍻', 'category' => 'Fundraiser', 'start' => '2026-10-17 16:00', 'end' => '2026-10-17 19:00', 'price' => '20',
		'tagline' => 'Raise a glass for shelter pups',
		'description' => '<p>An evening of local craft drinks, food trucks and a silent auction — every dollar from tickets goes to our local rescue partners for vet care and food.</p>',
		'sections' => array(
			'includes' => $rows( array( '🍻', 'Two drink tickets (21+)' ), array( '🌮', 'Food truck voucher' ), array( '🐶', 'Off-leash play for your dog' ) ),
			'goodies' => $gifts( array( '🏷️', 'Silent Auction', 'Bid on gift baskets, grooming packages and a free week of boarding.' ), array( '📸', 'Pup Portraits', 'A local photographer is taking portraits by donation.' ) ),
			'good_to_know' => $rows( array( '🪪', 'Bring your ID — drinks are for guests 21 and over.' ), $vax, $bite ),
		),
	),
	$park + array(
		'title' => 'Pup-kin Patch Photo Day', 'emoji' => '🎃', 'category' => 'Holiday party', 'start' => '2026-10-24 10:00', 'end' => '2026-10-24 14:00', 'price' => '15', 'spots' => '25', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'Fall photos in our pumpkin patch',
		'description' => '<p>We are turning the park into a pumpkin patch! Get professional photos of your pup among hay bales and pumpkins — just in time for your fall cards.</p>',
		'sections' => array(
			'sessions' => $sizes( '10:00', '12:00', '14:00' ),
			'includes' => $rows( array( '📸', 'Three edited digital photos' ), array( '🎃', 'A mini pumpkin to take home' ), array( '🍪', 'Pumpkin treats' ) ),
			'good_to_know' => array( 'rows' => array( $vax, $bite, array( 'emoji' => '📅', 'text' => 'Photos are emailed within a week.' ) ) ),
		),
	),
	$park + array(
		'title' => 'Dog CPR & First Aid Workshop', 'emoji' => '🩺', 'category' => 'Training', 'start' => '2026-10-29 18:30', 'end' => '2026-10-29 20:30', 'price' => '35', 'spots' => '16',
		'tagline' => 'Be ready when it matters most',
		'description' => '<p>A hands-on evening with a veterinary technician: CPR on training mannequins, choking response, bandaging, heat stroke and what belongs in a dog first aid kit.</p>',
		'sections' => array(
			'includes' => $rows( array( '🩺', 'Two hours of hands-on practice' ), array( '🧰', 'A starter first aid kit' ), array( '📜', 'A certificate of completion' ) ),
			'good_to_know' => array( 'rows' => array( $humans, array( 'emoji' => '☕', 'text' => 'Coffee and snacks are provided.' ) ) ),
		),
	),
	$park + array(
		'title' => 'Howl-o-ween Costume Parade', 'emoji' => '👻', 'category' => 'Holiday party', 'start' => '2026-10-31 10:00', 'end' => '2026-10-31 13:00', 'price' => '15', 'spots' => '40', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'Spooky, silly and seriously cute',
		'description' => '<p>Dress up your dog (and yourself!) for our biggest party of the fall. Parade down the runway, trick-or-treat at the treat stations and compete for prizes.</p>',
		'sections' => array(
			'sessions' => $sizes( '10:00', '11:30', '13:00' ),
			'includes' => $rows( array( '👻', 'The costume parade and contest' ), array( '🍬', 'Dog-safe trick-or-treat stations' ), array( '📸', 'A spooky photo booth' ), array( '🎁', 'Goodie bags' ) ),
			'goodies' => $gifts( array( '🏆', 'Costume Contest', 'Prizes for Scariest, Funniest and Best Dog-and-Owner Duo.' ) ),
			'good_to_know' => array( 'rows' => array( array( 'emoji' => '🎭', 'text' => 'Please choose costumes your dog can move, see and breathe in comfortably.' ), $vax, $bite ) ),
		),
	),
	$puppy_social( '2026-11-04' ),
	$adoption( '2026-11-07' ),
	$obedience( '2026-11-12', 'Leash Manners', 'Turn pulling into polite walking. We cover loose-leash walking, turns, stopping at curbs and passing other dogs calmly.' ),
	$park + array(
		'title' => 'Senior Dog Social', 'emoji' => '🦴', 'category' => 'Social', 'start' => '2026-11-14 10:00', 'end' => '2026-11-14 11:30', 'free' => true, 'spots' => '15',
		'tagline' => 'A gentle morning for dogs 8 years and older',
		'description' => '<p>Our grey-muzzled friends deserve a party too. The park is set up with soft mats, low obstacles and quiet corners for a relaxed, low-key social.</p>',
		'sections' => array(
			'includes' => $rows( array( '🛏️', 'Soft mats and quiet rest areas' ), array( '🦴', 'Senior-friendly soft treats' ), array( '🩺', 'Joint health tips from a vet tech' ) ),
			'good_to_know' => array( 'rows' => array( $vax, $water ) ),
		),
	),
	$park + array(
		'title' => 'Fur-iendsgiving Feast', 'emoji' => '🦃', 'category' => 'Holiday party', 'start' => '2026-11-21 11:00', 'end' => '2026-11-21 14:00', 'price' => '15', 'spots' => '30', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'A Thanksgiving feast made for dogs',
		'description' => '<p>Gather your four-legged family for a pup-friendly Thanksgiving: turkey and sweet potato bites, a gratitude photo wall and plenty of play.</p>',
		'sections' => array(
			'sessions' => $sizes( '11:00', '12:30', '14:00' ),
			'includes' => $rows( array( '🦃', 'A dog-safe Thanksgiving plate' ), array( '📸', 'Gratitude photo wall' ), array( '🐾', 'Off-leash play' ) ),
			'good_to_know' => array( 'rows' => array( array( 'emoji' => '🥧', 'text' => 'Tell us about food allergies when you sign up and we will prepare a plain plate.' ), $vax, $bite ) ),
		),
	),
	$park + array(
		'title' => 'Black Fur-day Grooming Pop-Up', 'emoji' => '🛁', 'category' => 'Grooming', 'start' => '2026-11-27 09:00', 'end' => '2026-11-27 15:00', 'price' => '20', 'spots' => '24',
		'tagline' => 'Bath, brush and blow-dry deals for the holidays',
		'description' => '<p>Get your dog holiday-ready. Our groomers offer a quick bath, brush-out and blow-dry, plus a festive bandana.</p>',
		'sections' => array(
			'sessions' => array(
				'rows' => array(
					array( 'from' => '09:00', 'to' => '12:00', 'name' => 'Morning', 'who' => 'All dogs' ),
					array( 'from' => '12:00', 'to' => '15:00', 'name' => 'Afternoon', 'who' => 'All dogs' ),
				),
			),
			'includes' => $rows( array( '🛁', 'Bath with oatmeal shampoo' ), array( '🪮', 'Brush-out and blow-dry' ), array( '🎀', 'A holiday bandana' ) ),
			'good_to_know' => $rows( $vax, array( '⏰', 'Each groom takes about 45 minutes — we will text you when your pup is ready.' ) ),
		),
	),
	$park + array(
		'title' => 'Holiday Photo Booth with Santa Paws', 'emoji' => '🎅', 'category' => 'Holiday party', 'start' => '2026-12-05 10:00', 'end' => '2026-12-05 15:00', 'price' => '20', 'spots' => '30', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => "Your pup's picture with Santa Paws",
		'description' => '<p>Santa Paws is visiting Paw-radise! Book a time for professional holiday photos with Santa — perfect for this year\'s holiday card.</p>',
		'sections' => array(
			'sessions' => array(
				'rows' => array(
					array( 'from' => '10:00', 'to' => '11:40', 'name' => 'Session 1', 'who' => 'All dogs' ),
					array( 'from' => '11:40', 'to' => '13:20', 'name' => 'Session 2', 'who' => 'All dogs' ),
					array( 'from' => '13:20', 'to' => '15:00', 'name' => 'Session 3', 'who' => 'All dogs' ),
				),
			),
			'includes' => $rows( array( '📸', 'Five edited digital photos' ), array( '🎅', 'Time with Santa Paws' ), array( '🍪', 'Holiday cookie for your pup' ) ),
			'good_to_know' => array( 'rows' => array( $vax, $bite, array( 'emoji' => '📅', 'text' => 'Photos are emailed within a week — in time for holiday cards.' ) ) ),
		),
	),
	$playgroup( '2026-12-09' ),
	$park + array(
		'title' => 'Toy Drive Pawty for Shelter Pups', 'emoji' => '🧸', 'category' => 'Fundraiser', 'start' => '2026-12-12 11:00', 'end' => '2026-12-12 14:00', 'free' => true,
		'tagline' => 'Bring a toy, play for free',
		'description' => '<p>Entry is free with a new dog toy or a bag of food. Everything collected goes to shelter dogs waiting for their forever homes this holiday season.</p>',
		'sections' => array(
			'includes' => $rows( array( '🐾', 'Off-leash play' ), array( '☕', 'Hot cocoa for the humans' ), array( '🎄', 'Holiday music and decorations' ) ),
			'goodies' => $gifts( array( '🎁', 'Donation Wishlist', 'Durable chew toys, puzzle feeders, blankets and grain-free kibble are most needed.' ) ),
			'good_to_know' => array( 'rows' => array( $vax, $bite ) ),
		),
	),
	$park + array(
		'title' => 'Yappy Hour: Ugly Sweater Edition', 'emoji' => '🧶', 'category' => 'Social', 'start' => '2026-12-16 17:30', 'end' => '2026-12-16 19:30', 'price' => '10', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'The uglier the sweater, the better',
		'description' => '<p>Dig out your tackiest holiday sweater — and one for your dog. Mocktails, music and a sweater contest with prizes for the most gloriously ugly outfits.</p>',
		'sections' => array(
			'includes' => $rows( array( '🍹', 'A holiday mocktail' ), array( '🧊', 'Pup-sicles' ), array( '🏆', 'The ugly sweater contest' ) ),
			'good_to_know' => array( 'rows' => array( $vax, $bite ) ),
		),
	),
	$park + array(
		'title' => 'Boxing Day Zoomies', 'emoji' => '🎁', 'category' => 'Social', 'start' => '2026-12-26 10:00', 'end' => '2026-12-26 13:00', 'price' => '10', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'Burn off the holiday treats',
		'description' => '<p>After all the holiday excitement, your dog needs a good run. Open play all morning, with new toys from the toy drive to try out.</p>',
		'sections' => array(
			'sessions' => $sizes( '10:00', '11:30', '13:00' ),
			'includes' => $rows( array( '🐾', 'Open off-leash play' ), array( '🎾', 'New toys to try out' ) ),
			'good_to_know' => array( 'rows' => array( $vax, $bite, $water ) ),
		),
	),
	$park + array(
		'title' => "New Year's Eve Pawty", 'emoji' => '🎉', 'category' => 'Holiday party', 'start' => '2026-12-31 16:00', 'end' => '2026-12-31 19:00', 'price' => '20', 'spots' => '35', 'coupon' => 'PAWPARK', 'note' => $member,
		'tagline' => 'Ring in the new year before the fireworks',
		'description' => '<p>Celebrate early with your dog! Party hats, a "pup-cider" toast and a confetti-free countdown at 6:30 PM — so everyone is home and calm before the fireworks start.</p>',
		'sections' => array(
			'sessions' => $sizes( '16:00', '17:30', '19:00' ),
			'includes' => $rows( array( '🎉', 'The early countdown and pup-cider toast' ), array( '🥳', 'Party hats and photo booth' ), array( '🎁', 'A calming chew to take home' ) ),
			'good_to_know' => array( 'rows' => array( array( 'emoji' => '🎆', 'text' => 'Fireworks tips: walk your dog before dark, close the curtains and play calm music at home.' ), $vax, $bite ) ),
		),
	),
	$park + array(
		'title' => 'New Year, New Tricks Workshop', 'emoji' => '✨', 'category' => 'Training', 'start' => '2027-01-09 10:00', 'end' => '2027-01-09 12:00', 'price' => '30', 'spots' => '10',
		'tagline' => 'Spin, shake, high-five and more',
		'description' => '<p>Start the year by learning something fun together. We teach three crowd-pleasing tricks step by step, with a practice plan to keep going at home.</p>',
		'sections' => array(
			'sessions' => $one( '10:00', '12:00', 'Dogs who know "sit"' ),
			'includes' => $rows( array( '✨', 'Three new tricks' ), array( '📄', 'A home practice plan' ), array( '🦴', 'Training treats' ) ),
			'good_to_know' => array( 'rows' => array( $vax, array( 'emoji' => '🍗', 'text' => 'Bring a hungry dog and their favorite small treats.' ) ) ),
		),
	),
	$puppy_social( '2027-01-13' ),
	$adoption( '2027-01-16' ),
	$park + array(
		'title' => 'Canine Nutrition Talk', 'emoji' => '🥕', 'category' => 'Training', 'start' => '2027-01-21 18:30', 'end' => '2027-01-21 19:30', 'free' => true, 'spots' => '30',
		'tagline' => 'What should really be in your dog\'s bowl?',
		'description' => '<p>A relaxed Q&amp;A with a pet nutritionist: reading food labels, raw vs. kibble, healthy treats and keeping your dog at a good weight.</p>',
		'sections' => array(
			'includes' => $rows( array( '🥕', 'A one-hour talk and Q&A' ), array( '📄', 'A food-label cheat sheet' ), array( '🍪', 'Healthy treat samples to take home' ) ),
			'good_to_know' => array( 'rows' => array( $humans ) ),
		),
	),
	array(
		'title' => 'Beach Morning at the Dog Beach', 'emoji' => '🏖️', 'category' => 'Social', 'start' => '2027-01-23 08:00', 'end' => '2027-01-23 10:00', 'free' => true,
		'venue' => 'Dog Beach', 'address' => 'Bonita Springs, FL',
		'tagline' => 'Sand, waves and zoomies with the Pawfectly pack',
		'description' => '<p>Join the Pawfectly crew for a cool winter morning at the dog beach. We bring towels, water and treats — you bring the zoomies.</p>',
		'sections' => array(
			'includes' => $rows( array( '🌊', 'Group beach walk with our team' ), array( '🧺', 'Towels and fresh water' ), array( '🍪', 'Treats after the swim' ) ),
			'good_to_know' => $rows( array( '🅿️', 'Parking fills up fast — please arrive by 7:45 AM.' ), array( '🦮', 'Dogs must be leashed until we reach the off-leash area.' ), $vax ),
		),
	),
	$park + array(
		'title' => 'Winter Agility Fun Run', 'emoji' => '🏃', 'category' => 'Training', 'start' => '2027-01-30 09:00', 'end' => '2027-01-30 12:00', 'price' => '25', 'spots' => '12',
		'tagline' => 'A friendly, timed course for every level',
		'description' => '<p>Put your skills to the test on a full agility course. Every dog gets two timed runs, and there are ribbons for every finisher — no experience needed.</p>',
		'sections' => array(
			'sessions' => array(
				'rows' => array(
					array( 'from' => '09:00', 'to' => '10:30', 'name' => 'Beginners', 'who' => 'New to agility' ),
					array( 'from' => '10:30', 'to' => '12:00', 'name' => 'Experienced', 'who' => 'Dogs who have run a full course' ),
				),
			),
			'includes' => $rows( array( '⏱️', 'Two timed runs' ), array( '🎀', 'A finisher ribbon' ), array( '📸', 'Action photos' ) ),
			'goodies' => $gifts( array( '🏆', 'Fastest Paws', 'Prizes for the fastest clean run in each group.' ) ),
			'good_to_know' => $rows( $vax, array( '🩺', 'Dogs should be at least 1 year old and in good health.' ) ),
		),
	),
);

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Question bank (3 levels x 10 questions) + result bands.
 * Developers can filter the bank with the `tjq_questions` filter.
 */
class TJQ_Questions {

	public static function levels() {
		return array(
			'beginner'     => array(
				'label'  => 'Beginner',
				'desc'   => 'Letters, vowels, signs and basic terms.',
				'course' => 'Quran Reading & Tajweed Foundations',
			),
			'intermediate' => array(
				'label'  => 'Intermediate',
				'desc'   => 'Noon & meem rules, madd and qalqalah.',
				'course' => 'Tajweed Rules in Practice',
			),
			'advanced'     => array(
				'label'  => 'Advanced',
				'desc'   => 'Madd types, heavy/light letters, ghunnah and waqf.',
				'course' => 'Advanced Recitation & Ijazah Preparation',
			),
		);
	}

	private static function q( $level, $n, $q, $options, $correct, $explain ) {
		// Spread the correct answer across positions (deterministic, so scoring stays stable).
		// Number-only lists (e.g. "2 counts") keep their natural ascending order.
		$numeric = true;
		foreach ( $options as $o ) {
			if ( ! preg_match( '/^\d/', $o ) ) {
				$numeric = false;
				break;
			}
		}
		if ( ! $numeric ) {
			$target = ( $n - 1 + ( 'beginner' === $level ? 0 : ( 'intermediate' === $level ? 1 : 2 ) ) ) % count( $options );
			if ( $target !== $correct ) {
				$tmp                 = $options[ $target ];
				$options[ $target ]  = $options[ $correct ];
				$options[ $correct ] = $tmp;
				$correct             = $target;
			}
		}
		return array(
			'id'      => substr( $level, 0, 1 ) . $n,
			'level'   => $level,
			'q'       => $q,
			'options' => $options,
			'correct' => $correct,
			'explain' => $explain,
		);
	}

	public static function all() {
		$b = 'beginner';
		$i = 'intermediate';
		$a = 'advanced';
		$q = array(
			// ---------- Beginner ----------
			self::q( $b, 1, 'How many letters are in the Arabic alphabet?', array( '26', '28', '30', '29' ), 1, 'The Arabic alphabet has 28 letters, and the Quran uses all of them.' ),
			self::q( $b, 2, 'What does the word "Tajweed" mean linguistically?', array( 'To memorise', 'To make something better / do it with excellence', 'To translate', 'To recite quickly' ), 1, 'Tajweed comes from a root meaning "to make better". In recitation it means giving every letter its due right.' ),
			self::q( $b, 3, 'Which three short vowels (harakat) are used in the Quran?', array( 'Fatha, Kasra, Damma', 'Alif, Waw, Ya', 'Sukoon, Shaddah, Madd', 'Tanween, Hamzah, Waqf' ), 0, 'Fatha (a), Kasra (i) and Damma (u) are the three short vowels.' ),
			self::q( $b, 4, 'What does a Sukoon (ْ) on a letter tell you?', array( 'The letter is doubled', 'The letter carries no vowel', 'The letter is stretched', 'The letter is skipped' ), 1, 'Sukoon marks a letter with no vowel. It is pronounced, but without a vowel sound.' ),
			self::q( $b, 5, 'What does a Shaddah (ّ) on a letter tell you?', array( 'The letter is doubled', 'You must stop on the letter', 'The letter is silent', 'The letter is stretched 6 counts' ), 0, 'Shaddah doubles the letter: a silent letter merged into the next voweled letter.' ),
			self::q( $b, 6, 'What is Tanween?', array( 'A double short vowel at the end of a noun that adds an "n" sound', 'A letter that is never pronounced', 'A sign for stopping', 'A type of prolongation' ), 0, 'Tanween (ً ٍ ٌ) is a doubled vowel mark that adds a light "n" sound at the end of a word.' ),
			self::q( $b, 7, 'Which are the three letters of Madd (long vowels)?', array( 'ا و ي', 'ب ت ث', 'ق ط ب', 'ء ه ع' ), 0, 'Alif, Waw and Ya stretch the vowel before them when they follow a matching vowel.' ),
			self::q( $b, 8, 'What does "Makharij" mean?', array( 'The points of articulation: where letters come out', 'The rules of stopping', 'The types of prolongation', 'The names of the chapters' ), 0, 'Makharij al-huruf are the places in the throat, mouth, tongue, lips and nasal passage where letters are produced.' ),
			self::q( $b, 9, 'What does "Waqf" mean in recitation?', array( 'Stopping or pausing', 'Doubling', 'Lengthening', 'Whispering' ), 0, 'Waqf is stopping at the end of a word, normally with a sukoon and a breath.' ),
			self::q( $b, 10, 'Which letter is pronounced with the lips?', array( 'ب', 'ك', 'ع', 'ر' ), 0, 'ب (Ba), م (Meem) and و (Waw) are lip letters.' ),

			// ---------- Intermediate ----------
			self::q( $i, 1, 'When Noon Sakinah (نْ) or tanween is followed by the letter ب, the rule is:', array( 'Izhar', 'Idgham', 'Iqlab', 'Ikhfa' ), 2, 'Iqlab: the noon sound is changed into a hidden meem with ghunnah.' ),
			self::q( $i, 2, 'How many throat letters (Izhar Halqi) are there? (ء ه ع ح غ خ)', array( '4', '6', '8', '15' ), 1, 'There are six throat letters. After them, noon sakinah/tanween is pronounced clearly (Izhar).' ),
			self::q( $i, 3, 'Which letters give Idgham WITH ghunnah (the word يَنْمُو)?', array( 'ي ن م و', 'ل ر', 'ق ط ب ج د', 'ء ه ع ح غ خ' ), 0, 'Ya, Noon, Meem and Waw merge with the noon sound and keep the ghunnah.' ),
			self::q( $i, 4, 'How long is a natural prolongation (Madd Asli)?', array( '1 count', '2 counts', '4 counts', '6 counts' ), 1, 'Madd Asli (natural madd) is held for 2 counts (harakat).' ),
			self::q( $i, 5, 'Which letters make up Qalqalah?', array( 'ق ط ب ج د', 'ء ه ع ح', 'ل ر', 'ي ن م و' ), 0, 'The five Qalqalah letters are remembered with the phrase قُطْبُ جَدٍّ. They echo when silent.' ),
			self::q( $i, 6, 'When Meem Sakinah (مْ) is followed by ب, the rule is:', array( 'Izhar Shafawi', 'Idgham Shafawi', 'Ikhfa Shafawi', 'Iqlab' ), 2, 'Ikhfa Shafawi: the meem is hidden with ghunnah before ب, with the lips lightly touching.' ),
			self::q( $i, 7, 'Idgham WITHOUT ghunnah happens when Noon Sakinah meets:', array( 'ل and ر', 'ي and و', 'م and ن', 'ب and ف' ), 0, 'Before Lam and Ra, the noon merges completely with no ghunnah.' ),
			self::q( $i, 8, 'How long is a standard ghunnah?', array( '1 count', '2 counts', '4 counts', '6 counts' ), 1, 'Ghunnah, the nasal sound, is held for about 2 counts.' ),
			self::q( $i, 9, 'When Noon Sakinah is followed by the letter ت, the rule is:', array( 'Izhar', 'Idgham', 'Ikhfa', 'Iqlab' ), 2, 'ت is one of the 15 Ikhfa letters: the noon is hidden with ghunnah.' ),
			self::q( $i, 10, 'When Meem Sakinah (مْ) is followed by another م, the rule is:', array( 'Izhar Shafawi', 'Idgham Shafawi', 'Ikhfa Shafawi', 'Iqlab' ), 1, 'Two meems merge into one doubled meem with ghunnah: Idgham Shafawi.' ),

			// ---------- Advanced ----------
			self::q( $a, 1, 'How many letters cause Ikhfa after Noon Sakinah or tanween?', array( '6', '10', '15', '28' ), 2, 'Ikhfa applies before 15 letters.' ),
			self::q( $a, 2, 'Madd Muttasil (connected madd) happens when:', array( 'A hamzah follows the madd letter in the same word', 'A hamzah follows the madd letter in the next word', 'A sukoon follows a madd letter', 'A shaddah follows a noon' ), 0, 'Muttasil = connected: madd letter and hamzah are in the same word. The stretch is obligatory (commonly 4 to 5 counts).' ),
			self::q( $a, 3, 'Which group are the heavy letters (Isti\'la)?', array( 'خ ص ض غ ط ق ظ', 'ء ه ع ح', 'ب ت ث', 'ل ر' ), 0, 'The seven heavy letters are remembered with خُصَّ ضَغْطٍ قِظْ.' ),
			self::q( $a, 4, 'Madd Munfasil (separated madd) happens when:', array( 'A madd letter ends a word and the next word starts with a hamzah', 'A madd letter and hamzah are in the same word', 'A madd letter is followed by a permanent sukoon', 'A madd letter is followed by a shaddah' ), 0, 'Munfasil = separated: the madd letter and the hamzah are in two different words. The stretch is permissible (2, 4 or 5 counts depending on the narration).' ),
			self::q( $a, 5, 'How many counts is Madd Lazim (necessary madd)?', array( '2', '4', '5', '6' ), 3, 'A madd letter followed by a permanent sukoon or shaddah in the same word is held for 6 counts.' ),
			self::q( $a, 6, 'Qalqalah is strongest (Kubra) when:', array( 'You stop on the Qalqalah letter', 'The letter is in the middle of a word with sukoon', 'The letter carries a fatha', 'The letter is followed by a noon' ), 0, 'When you stop on a Qalqalah letter, the echo is at its strongest.' ),
			self::q( $a, 7, 'A Ra with a Fatha is pronounced:', array( 'Heavy (tafkheem)', 'Light (tarqeeq)', 'Silent', 'Doubled' ), 0, 'Ra with a fatha or damma is heavy; with a kasra it is usually light.' ),
			self::q( $a, 8, 'The Lam in the word "Allah" is pronounced heavy when it comes after:', array( 'A fatha or damma', 'A kasra', 'A sukoon', 'A tanween' ), 0, 'After a fatha or damma the Lam is heavy; after a kasra it is light.' ),
			self::q( $a, 9, 'The fullest ghunnah occurs on:', array( 'A Noon or Meem with shaddah', 'Noon sakinah before Izhar letters', 'Lam sakinah', 'Ra sakinah' ), 0, 'A noon or meem carrying a shaddah has the complete ghunnah.' ),
			self::q( $a, 10, 'When you stop on a word ending with Ta Marbuta (ة), it is pronounced as:', array( 'Ha (ه) with sukoon', 'Ta (ت)', 'Silent', 'Alif' ), 0, 'In waqf, the Ta Marbuta is pronounced like a soft Ha.' ),
		);
		$q = array_merge( $q, self::custom() );
		return apply_filters( 'tjq_questions', $q );
	}

	/** Questions added in the admin (Tajweed Quiz > Questions), optionally with an audio clip. */
	public static function custom() {
		$saved  = get_option( 'tjq_custom_questions', array() );
		$levels = self::levels();
		$out    = array();
		if ( ! is_array( $saved ) ) {
			return $out;
		}
		foreach ( array_values( $saved ) as $n => $c ) {
			if ( empty( $c['q'] ) || empty( $c['options'] ) || ! isset( $levels[ $c['level'] ] ) ) {
				continue;
			}
			$out[] = array(
				'id'      => 'c' . ( $n + 1 ),
				'level'   => $c['level'],
				'q'       => $c['q'],
				'options' => array_values( $c['options'] ),
				'correct' => (int) $c['correct'],
				'explain' => isset( $c['explain'] ) ? $c['explain'] : '',
				'audio'   => isset( $c['audio'] ) ? $c['audio'] : '',
			);
		}
		return $out;
	}

	/** Questions for the browser (no answers), grouped by level. */
	public static function public_by_level() {
		$out = array();
		foreach ( self::all() as $q ) {
			$row = array(
				'id'      => $q['id'],
				'q'       => $q['q'],
				'options' => $q['options'],
			);
			if ( ! empty( $q['audio'] ) ) {
				$row['audio'] = $q['audio'];
			}
			$out[ $q['level'] ][] = $row;
		}
		$out['kids'] = self::kids_public();
		return $out;
	}

	/** All selectable tests: three levels plus the parents' readiness test. */
	public static function tests() {
		return array_merge(
			self::levels(),
			array(
				'kids' => array(
					'label'  => 'Kids readiness',
					'desc'   => 'Is my child ready to start Quran lessons?',
					'course' => 'Quran for Kids: Reading, Tajweed & Hifz',
				),
			)
		);
	}

	/**
	 * Parents' readiness test. Options carry points (not right/wrong answers).
	 * Questions with all-zero points are profiling questions (stored, not scored).
	 */
	public static function kids() {
		return apply_filters(
			'tjq_kids_questions',
			array(
				array( 'id' => 'k1', 'key' => 'age', 'q' => 'How old is your child?', 'options' => array( '4 to 5', '6 to 7', '8 to 12', '13 or older' ), 'points' => array( 1, 2, 3, 3 ) ),
				array( 'id' => 'k2', 'key' => 'letters', 'q' => 'Does your child recognise Arabic letters?', 'options' => array( 'Not yet', 'A few', 'Most of them', 'All of them' ), 'points' => array( 0, 1, 2, 3 ) ),
				array( 'id' => 'k3', 'key' => 'focus', 'q' => 'How long can your child focus in an online lesson?', 'options' => array( 'About 10 minutes', 'About 20 minutes', '30 minutes or more' ), 'points' => array( 1, 2, 3 ) ),
				array( 'id' => 'k4', 'key' => 'reading', 'q' => 'Has your child learned to read the Quran before?', 'options' => array( 'Never', 'Started Qaida / the basics', 'Reads with help', 'Reads fluently' ), 'points' => array( 0, 1, 2, 3 ) ),
				array( 'id' => 'k5', 'key' => 'sessions', 'q' => 'How many lessons per week can you commit to?', 'options' => array( '1', '2', '3 or more' ), 'points' => array( 1, 2, 3 ) ),
				array( 'id' => 'k6', 'key' => 'goal', 'q' => 'What is your main goal for your child?', 'options' => array( 'Learn to read the Quran', 'Read with correct Tajweed', 'Memorise (Hifz)', 'Arabic language' ), 'points' => array( 0, 0, 0, 0 ) ),
				array( 'id' => 'k7', 'key' => 'teacher', 'q' => 'Do you prefer a male or a female teacher?', 'options' => array( 'Female teacher', 'Male teacher', 'No preference' ), 'points' => array( 0, 0, 0 ) ),
			)
		);
	}

	public static function kids_public() {
		$out = array();
		foreach ( self::kids() as $k ) {
			$out[] = array(
				'id'      => $k['id'],
				'q'       => $k['q'],
				'options' => $k['options'],
			);
		}
		return $out;
	}

	public static function kids_max() {
		$max = 0;
		foreach ( self::kids() as $k ) {
			$max += max( $k['points'] );
		}
		return $max;
	}

	public static function kids_band( $score, $total ) {
		$pct = $total > 0 ? $score / $total : 0;
		if ( $pct >= 0.7 ) {
			$band  = 'ready';
			$label = 'Ready to start';
			$msg   = 'Your child has the foundations to begin a full programme now: reading with Tajweed, and Hifz when they are ready. A free trial lesson is the best way to find the right teacher and pace.';
			$plan  = 'Reading & Tajweed track, 2 to 3 lessons a week';
		} elseif ( $pct >= 0.35 ) {
			$band  = 'almost';
			$label = 'Ready with a gentle start';
			$msg   = 'Your child is nearly there. Shorter lessons, lots of repetition and a patient teacher will build confidence quickly.';
			$plan  = 'Qaida + short 20-minute lessons, 2 a week';
		} else {
			$band  = 'foundation';
			$label = 'Start with the basics';
			$msg   = 'The best start is the Arabic letters and their sounds, taught through short, playful sessions. There is no pressure: every child begins somewhere.';
			$plan  = 'Arabic letters & sounds foundation, short sessions';
		}
		$tests = self::tests();
		return array(
			'band'         => $band,
			'level'        => 'kids',
			'level_label'  => $tests['kids']['label'],
			'label'        => 'Kids: ' . $label,
			'message'      => $msg,
			'course_level' => 'kids',
			'course'       => $tests['kids']['course'] . ' (' . $plan . ')',
			'subject'      => 'Your child\'s Quran readiness result',
		);
	}

	/** Result info for any test. */
	public static function info( $level, $score, $total ) {
		return 'kids' === $level ? self::kids_band( $score, $total ) : self::band_for( $level, $score, $total );
	}

	public static function for_level( $level ) {
		return array_values(
			array_filter(
				self::all(),
				function ( $q ) use ( $level ) {
					return $q['level'] === $level;
				}
			)
		);
	}

	public static function goals() {
		return apply_filters(
			'tjq_goals',
			array(
				'scratch' => 'I want to learn to read the Quran from scratch',
				'improve' => 'I can read, I want to fix my Tajweed',
				'kids'    => 'I am looking for classes for my child',
				'hifz'    => 'I want to memorise (Hifz)',
			)
		);
	}

	/**
	 * Result band for a level test.
	 * 80%+ : ready to move up. 50-79%: solid, needs polish. Below 50%: go back to basics.
	 */
	public static function band_for( $level, $score, $total ) {
		$levels = self::levels();
		$order  = array_keys( $levels );
		$idx    = array_search( $level, $order, true );
		$idx    = false === $idx ? 0 : $idx;
		$pct    = $total > 0 ? $score / $total : 0;

		if ( $pct >= 0.8 ) {
			$band   = 'strong';
			$label  = 'Excellent';
			$target = min( $idx + 1, count( $order ) - 1 );
			$msg    = $idx < count( $order ) - 1
				? 'You have a strong grasp of ' . $levels[ $level ]['label'] . ' Tajweed. You are ready to move up to the next level.'
				: 'You know the advanced rules very well. Live correction with a teacher is what turns knowledge into flawless recitation and prepares you for an ijazah.';
		} elseif ( $pct >= 0.5 ) {
			$band   = 'solid';
			$label  = 'Good, needs polish';
			$target = $idx;
			$msg    = 'You know a good part of the ' . $levels[ $level ]['label'] . ' material. A few gaps are holding your recitation back. Reviewing them with a teacher will close them quickly.';
		} else {
			$band   = 'practice';
			$label  = 'Needs practice';
			$target = max( $idx - 1, 0 );
			$msg    = 'This level is still a stretch, and that is completely fine. Building a solid base first will make everything after it much easier.';
		}

		$course_level = $order[ $target ];
		$info         = array(
			'band'         => $band,
			'level'        => $level,
			'level_label'  => $levels[ $level ]['label'],
			'label'        => $levels[ $level ]['label'] . ': ' . $label,
			'message'      => $msg,
			'course_level' => $course_level,
			'course'       => $levels[ $course_level ]['course'],
			'subject'      => 'Your Tajweed result: ' . $levels[ $level ]['label'] . ' - ' . $label,
		);
		return apply_filters( 'tjq_level_info', $info, $score, $total );
	}
}

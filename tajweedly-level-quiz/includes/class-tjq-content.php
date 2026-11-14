<?php
defined( 'ABSPATH' ) || exit;

/**
 * Educational landing pages (rules, makharij, glossary, reader, Ramadan) generated as DRAFTS.
 *
 * Content policy: only widely agreed, basic rules are written here. Quran text is never hard-coded:
 * live examples come from the cached AlQuran Cloud text through [tajweedly_rule_examples], and the
 * few static example words are classic textbook words that are also checked by the plugin's tests.
 * Internal links use {{url:slug}} placeholders, resolved when the page is displayed.
 */
class TJQ_Content {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'resolve_links' ), 5 );
	}

	public static function resolve_links( $content ) {
		if ( false === strpos( $content, '{{url:' ) ) {
			return $content;
		}
		return preg_replace_callback(
			'/\{\{url:([a-z0-9-]+)\}\}/',
			function ( $m ) {
				$p = get_page_by_path( $m[1], OBJECT, 'page' );
				return $p ? esc_url( get_permalink( $p ) ) : '#';
			},
			$content
		);
	}

	private static function ar( $t ) {
		return '<span class="ar" lang="ar" dir="rtl">' . $t . '</span>';
	}

	/** Letter sets used on several pages (also verified by tests). */
	public static function letters() {
		return array(
			'izhar'    => array( 'ء', 'ه', 'ع', 'ح', 'غ', 'خ' ),
			'idgham'   => array( 'ي', 'ر', 'م', 'ل', 'و', 'ن' ),
			'idgham_g' => array( 'ي', 'ن', 'م', 'و' ),
			'idgham_n' => array( 'ل', 'ر' ),
			'ikhfa'    => array( 'ت', 'ث', 'ج', 'د', 'ذ', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ف', 'ق', 'ك' ),
			'sun'      => array( 'ت', 'ث', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ل', 'ن' ),
			'moon'     => array( 'ا', 'ب', 'ج', 'ح', 'خ', 'ع', 'غ', 'ف', 'ق', 'ك', 'م', 'ه', 'و', 'ي' ),
			'qalqalah' => array( 'ق', 'ط', 'ب', 'ج', 'د' ),
			'istila'   => array( 'خ', 'ص', 'ض', 'غ', 'ط', 'ق', 'ظ' ),
		);
	}

	private static function row( $cells ) {
		return '<tr><td>' . implode( '</td><td>', $cells ) . '</td></tr>';
	}

	private static function noon_table() {
		$L = self::letters();
		$j = function ( $a ) {
			return self::ar( implode( ' ', $a ) );
		};
		return '<table><thead><tr><th>Rule</th><th>Letters after the noon</th><th>What you do</th></tr></thead><tbody>'
			. self::row( array( '<a href="{{url:izhar-tajweed-rules}}">Izhar</a>', $j( $L['izhar'] ), 'Say the noon clearly, no nasal humming' ) )
			. self::row( array( '<a href="{{url:idgham-tajweed-rules}}">Idgham</a>', $j( $L['idgham'] ), 'Merge into the next letter (with or without ghunnah)' ) )
			. self::row( array( '<a href="{{url:iqlab-tajweed-rules}}">Iqlab</a>', self::ar( 'ب' ), 'Turn the noon into a hidden meem, with ghunnah' ) )
			. self::row( array( '<a href="{{url:ikhfa-tajweed-rules}}">Ikhfa</a>', $j( $L['ikhfa'] ), 'Hide the noon, with ghunnah' ) )
			. '</tbody></table>';
	}

	private static function faq_block( array $faq ) {
		$out = '<h2>Frequently asked questions</h2>';
		foreach ( $faq as $f ) {
			$out .= '<h3>' . esc_html( $f[0] ) . '</h3><p>' . esc_html( $f[1] ) . '</p>';
		}
		return $out;
	}

	/** Internal links shown at the end of every learning page (helps crawling and keeps readers moving). */
	private static function related_block() {
		return '<h2>Keep learning</h2><ul>'
			. '<li><a href="{{url:tajweed-rules}}">Tajweed rules explained</a></li>'
			. '<li><a href="{{url:quran-with-tajweed-colors}}">Read the Quran with tajweed colours</a></li>'
			. '<li><a href="{{url:tajweed-glossary}}">Tajweed glossary</a></li>'
			. '<li><a href="{{url:tajweed-test}}">Free Tajweed level test</a></li></ul>';
	}

	private static function wrap( $inner, $faq, $examples = '' ) {
		return '<div class="tjq-page">[tajweedly_lesson_top]' . $inner . ( $examples ? '[tajweedly_rule_examples codes="' . $examples . '"]' : '' )
			. '[tajweedly_lesson_end][tajweedly_tip]' . ( $faq ? self::faq_block( $faq ) : '' )
			. self::related_block()
			. '<p>Want a teacher to check your recitation? Take the <a href="{{url:tajweed-test}}">free Tajweed test</a> and book a free trial lesson.</p></div>';
	}

	/** Final page HTML for a spec (adds the live examples, daily tip, FAQ and the test link). */
	public static function compose( array $spec ) {
		if ( ! empty( $spec['plain'] ) ) {
			return '<div class="tjq-page">' . $spec['body'] . '</div>';
		}
		return self::wrap( $spec['body'], isset( $spec['faq'] ) ? $spec['faq'] : array(), isset( $spec['codes'] ) ? $spec['codes'] : '' );
	}

	/** Sources of the slug => spec for every educational page. */
	public static function specs() {
		$L = self::letters();
		$j = function ( $arr ) {
			return self::ar( implode( ' ', $arr ) );
		};
		$specs = array();

		$specs['tajweed-rules'] = array(
			'title' => 'Tajweed Rules Explained: A Simple Guide for Beginners',
			'desc'  => 'A beginner-friendly guide to the main tajweed rules: noon sakinah, meem sakinah, madd, ghunnah, qalqalah and more, with a free test.',
			'faq'   => array(
				array( 'What are the main tajweed rules?', 'The core groups are the rules of noon sakinah and tanween, meem sakinah, ghunnah, qalqalah, madd (prolongation), the sun and moon letters, and the points of articulation (makharij).' ),
				array( 'Where should a beginner start?', 'Start with the letters and their sounds, then the short vowels, sukoon and shaddah, then the noon sakinah rules. A teacher who can hear you is the fastest way to correct mistakes.' ),
			),
			'body'  => '<p>Tajweed means reciting the Quran with every letter given its proper sound, length and quality. These pages explain the main rules in plain English. Start here and follow the lessons in order.</p>'
				. '<h2>Your learning path</h2>[tajweedly_path]'
				. '<h2>Explore the letters</h2><p>Tap a rule to see which letters it applies to.</p>[tajweedly_explorer]'
				. '<h2>The main rule groups</h2><ul>'
				. '<li><a href="{{url:izhar-tajweed-rules}}">Izhar</a>, <a href="{{url:idgham-tajweed-rules}}">Idgham</a>, <a href="{{url:iqlab-tajweed-rules}}">Iqlab</a> and <a href="{{url:ikhfa-tajweed-rules}}">Ikhfa</a>: what to do with a noon sakinah or tanween</li>'
				. '<li><a href="{{url:meem-sakinah-tajweed-rules}}">Meem sakinah</a>: three rules</li>'
				. '<li><a href="{{url:ghunnah-tajweed-rules}}">Ghunnah</a>: the nasal sound</li>'
				. '<li><a href="{{url:qalqalah-tajweed-rules}}">Qalqalah</a>: the echo letters</li>'
				. '<li><a href="{{url:madd-tajweed-rules}}">Madd</a>: prolongation and its types</li>'
				. '<li><a href="{{url:sun-and-moon-letters}}">Sun and moon letters</a>: how to read "al-"</li>'
				. '<li><a href="{{url:makharij-al-huruf}}">Makharij</a>: where each letter comes from</li>'
				. '</ul><h2>Noon sakinah and tanween at a glance</h2>' . self::noon_table()
				. '<h2>Practise now</h2>[tajweedly_drill type="noon"]<h2>See the rules in the Quran</h2><p>Open the <a href="{{url:quran-with-tajweed-colors}}">colour-coded Quran</a>: every rule has its own colour. Not sure where you stand? Try the <a href="{{url:tajweed-test}}">free Tajweed test</a>.</p>'
				. '<p><a href="{{url:tajweed-glossary}}">Tajweed glossary</a>: every term in one place.</p>',
		);

		$specs['izhar-tajweed-rules'] = array(
			'title' => 'Izhar in Tajweed: Rule, Letters and Examples',
			'desc'  => 'Izhar means clarity: pronounce noon sakinah or tanween clearly before the six throat letters. Letters, how to read it and examples.',
			'faq'   => array(
				array( 'What is Izhar in tajweed?', 'Izhar means "clarity". When a noon sakinah or tanween is followed by a throat letter, the noon is pronounced clearly, without merging and without ghunnah.' ),
				array( 'Which letters cause Izhar?', 'The six throat letters: hamzah, ha, ain, ha (the heavy one), ghain and kha.' ),
			),
			'body'  => '<p><strong>Izhar</strong> means clarity. If a noon sakinah (' . self::ar( 'نْ' ) . ') or a tanween is followed by one of the six throat letters, pronounce the noon clearly: no merging, no hiding, no nasal humming.</p>'
				. '<h2>The letters</h2><p>' . $j( $L['izhar'] ) . '</p><p>They are called the throat letters (Izhar Halqi) because they are produced in the throat, far from where the noon is produced, so the noon stays clear.</p>'
				. '<h2>Examples</h2><ul><li>' . self::ar( 'مَنْ آمَنَ' ) . ' (noon followed by hamzah)</li><li>' . self::ar( 'أَنْعَمْتَ' ) . ' (noon followed by ain)</li></ul>'
				. '<h2>How to read it</h2><p>Say the noon from its normal place with a short, clean sound, then move straight to the throat letter. Do not hum through the nose.</p>'
				. '<h2>All four noon rules</h2>' . self::noon_table(),
			'codes' => '',
		);

		$specs['idgham-tajweed-rules'] = array(
			'title' => 'Idgham in Tajweed: With and Without Ghunnah, Letters and Examples',
			'desc'  => 'Idgham means merging. Learn the six idgham letters (yarmaloon), which merge with ghunnah and which without, with examples.',
			'faq'   => array(
				array( 'What does Idgham mean?', 'Idgham means merging: the noon sakinah or tanween is merged into the letter that follows, so the two sound like one doubled letter.' ),
				array( 'Which letters cause Idgham?', 'The six letters of the word "yarmaloon": ya, ra, meem, lam, waw and noon.' ),
				array( 'When is there no Idgham?', 'If the noon sakinah and the ya or waw are in the same word, the noon is pronounced clearly instead, as in ' . 'الدُّنْيَا' . '.' ),
			),
			'body'  => '<p><strong>Idgham</strong> means merging. When a noon sakinah or tanween meets one of six letters, the noon is merged into the next letter and that letter is pronounced doubled.</p>'
				. '<h2>The six letters</h2><p>' . $j( $L['idgham'] ) . ' (remembered as ' . self::ar( 'يَرْمَلُونَ' ) . ')</p>'
				. '<table><thead><tr><th>Type</th><th>Letters</th><th>Ghunnah?</th></tr></thead><tbody>'
				. self::row( array( 'Idgham with ghunnah', $j( $L['idgham_g'] ) . ' (' . self::ar( 'يَنْمُو' ) . ')', 'Yes, about 2 counts' ) )
				. self::row( array( 'Idgham without ghunnah', $j( $L['idgham_n'] ), 'No' ) )
				. '</tbody></table>'
				. '<h2>Examples</h2><ul><li>' . self::ar( 'مَن يَقُولُ' ) . ' (with ghunnah, noon before ya)</li><li>' . self::ar( 'مِن رَّبِّهِمْ' ) . ' (without ghunnah, noon before ra)</li><li>' . self::ar( 'هُدًى لِّلْمُتَّقِينَ' ) . ' (tanween before lam, without ghunnah)</li></ul>'
				. '<h2>The exception</h2><p>If the noon sakinah and the ya or waw are in the <em>same word</em>, there is no merging: the noon is read clearly. A well-known example is ' . self::ar( 'الدُّنْيَا' ) . '.</p>'
				. '<h2>All four noon rules</h2>' . self::noon_table(),
			'codes' => 'a,u',
		);

		$specs['iqlab-tajweed-rules'] = array(
			'title' => 'Iqlab in Tajweed: Rule, Letter and Examples',
			'desc'  => 'Iqlab means changing: noon sakinah or tanween before ba becomes a hidden meem with ghunnah. How to read it, with examples.',
			'faq'   => array(
				array( 'What is Iqlab?', 'Iqlab means "changing". Before the letter ba, a noon sakinah or tanween is changed into a meem sound, hidden, with ghunnah.' ),
				array( 'How long is the ghunnah in Iqlab?', 'About 2 counts.' ),
			),
			'body'  => '<p><strong>Iqlab</strong> means changing. When a noon sakinah or tanween is followed by ' . self::ar( 'ب' ) . ', the noon sound is changed into a meem, which is hidden, and held with ghunnah.</p>'
				. '<h2>How to read it</h2><ol><li>Do not say a clear noon.</li><li>Close the lips lightly, as for the letter ba.</li><li>Hold the nasal sound for about 2 counts, then release into the ba.</li></ol>'
				. '<h2>Examples</h2><ul><li>' . self::ar( 'مِن بَعْدِ' ) . '</li><li>' . self::ar( 'أَنۢبِئْهُم' ) . '</li></ul>'
				. '<h2>All four noon rules</h2>' . self::noon_table(),
			'codes' => 'i',
		);

		$specs['ikhfa-tajweed-rules'] = array(
			'title' => 'Ikhfa in Tajweed: The 15 Letters, Rule and Examples',
			'desc'  => 'Ikhfa means hiding: noon sakinah or tanween before 15 letters is hidden with ghunnah. The letters, a memory phrase and examples.',
			'faq'   => array(
				array( 'What is Ikhfa?', 'Ikhfa means "hiding". The noon sakinah or tanween is pronounced between a clear noon and a merged one, with ghunnah, before 15 letters.' ),
				array( 'How many letters cause Ikhfa?', 'Fifteen.' ),
			),
			'body'  => '<p><strong>Ikhfa</strong> means hiding. Before the 15 letters below, the noon sakinah or tanween is not pronounced clearly and not merged: it is hidden, with a light ghunnah, while the tongue prepares for the next letter.</p>'
				. '<h2>The 15 letters</h2><p>' . $j( $L['ikhfa'] ) . '</p><p>A classic memory phrase: ' . self::ar( 'صِفْ ذَا ثَنَا كَمْ جَادَ شَخْصٌ قَدْ سَمَا دُمْ طَيِّبًا زِدْ فِي تُقًى ضَعْ ظَالِمًا' ) . ' (the first letter of each word).</p>'
				. '<h2>How to read it</h2><p>The tongue does not touch the roof of the mouth where the noon is normally made. Hold a nasal sound for about 2 counts, shaped by the following letter.</p>'
				. '<h2>Examples</h2><ul><li>' . self::ar( 'مَن كَانَ' ) . '</li><li>' . self::ar( 'أَنتُمْ' ) . '</li></ul>'
				. '<h2>All four noon rules</h2>' . self::noon_table(),
			'codes' => 'f',
		);

		$specs['meem-sakinah-tajweed-rules'] = array(
			'title' => 'Meem Sakinah Rules in Tajweed: Ikhfa, Idgham and Izhar Shafawi',
			'desc'  => 'The three rules of meem sakinah: Ikhfa Shafawi before ba, Idgham Shafawi before meem, and Izhar Shafawi before all other letters.',
			'faq'   => array(
				array( 'How many rules does Meem Sakinah have?', 'Three: Ikhfa Shafawi (before ba), Idgham Shafawi (before meem) and Izhar Shafawi (before every other letter).' ),
			),
			'body'  => '<p>A meem with a sukoon (' . self::ar( 'مْ' ) . ') has three rules, depending on the letter that follows. They are called "shafawi" (of the lips) because the meem is a lip letter.</p>'
				. '<table><thead><tr><th>Rule</th><th>Next letter</th><th>What you do</th></tr></thead><tbody>'
				. self::row( array( 'Ikhfa Shafawi', self::ar( 'ب' ), 'Hide the meem with ghunnah; lips touch lightly' ) )
				. self::row( array( 'Idgham Shafawi', self::ar( 'م' ), 'Merge the two meems with ghunnah' ) )
				. self::row( array( 'Izhar Shafawi', 'Any other letter', 'Say the meem clearly, with extra care before ' . self::ar( 'و' ) . ' and ' . self::ar( 'ف' ) ) )
				. '</tbody></table>'
				. '<h2>Examples</h2><ul><li>' . self::ar( 'تَرْمِيهِم بِحِجَارَةٍ' ) . ' (Ikhfa Shafawi)</li><li>' . self::ar( 'هُمْ فِيهَا' ) . ' (Izhar Shafawi, care before fa)</li></ul>',
			'codes' => 'c,w',
		);

		$specs['ghunnah-tajweed-rules'] = array(
			'title' => 'Ghunnah in Tajweed: What It Is and How Long to Hold It',
			'desc'  => 'Ghunnah is the nasal sound of noon and meem. Where it appears, how long to hold it (2 counts), with examples.',
			'faq'   => array(
				array( 'What is Ghunnah?', 'Ghunnah is a nasal sound that comes from the nasal passage and accompanies the letters noon and meem.' ),
				array( 'How long is Ghunnah?', 'About 2 counts. It is strongest on a noon or meem carrying a shaddah.' ),
			),
			'body'  => '<p><strong>Ghunnah</strong> is the nasal sound that comes from the nose and accompanies noon and meem. It is held for about 2 counts.</p>'
				. '<h2>Where it is strongest</h2><p>On a noon or a meem with a shaddah: ' . self::ar( 'إِنَّ' ) . ' and ' . self::ar( 'ثُمَّ' ) . '.</p>'
				. '<h2>Where it also appears</h2><ul><li>Ikhfa, Iqlab and Idgham with ghunnah (noon rules)</li><li>Ikhfa Shafawi and Idgham Shafawi (meem rules)</li></ul>'
				. '<p>See also: <a href="{{url:tajweed-rules}}">all tajweed rules</a>.</p>',
			'codes' => 'g',
		);

		$specs['qalqalah-tajweed-rules'] = array(
			'title' => 'Qalqalah in Tajweed: The 5 Letters and How to Read Them',
			'desc'  => 'Qalqalah is an echo on the letters qaf, ta (heavy), ba, jim and dal when they carry a sukoon. Letters, types and examples.',
			'faq'   => array(
				array( 'What are the Qalqalah letters?', 'Qaf, taa (the heavy one), ba, jim and dal, remembered as qutbu jadin.' ),
				array( 'When is Qalqalah strongest?', 'When you stop on the letter at the end of a word, as in the end of the word ahad.' ),
			),
			'body'  => '<p><strong>Qalqalah</strong> is a light echo or bounce heard when one of five letters has a sukoon.</p>'
				. '<h2>The letters</h2><p>' . $j( $L['qalqalah'] ) . ' (' . self::ar( 'قُطْبُ جَدٍّ' ) . ')</p>'
				. '<h2>Levels</h2><ul><li>Lighter: when the letter has a sukoon in the middle of a word, as in ' . self::ar( 'يَقْطَعُونَ' ) . '.</li><li>Stronger: when you stop on the letter, as in ' . self::ar( 'أَحَدٌ' ) . ', ' . self::ar( 'الْفَلَقِ' ) . ' and ' . self::ar( 'وَتَبَّ' ) . '.</li></ul>'
				. '<h2>Tip</h2><p>Echo the letter without adding a vowel: it should not become "ba-a" or "da-a".</p>',
			'codes' => 'q',
		);

		$specs['madd-tajweed-rules'] = array(
			'title' => 'Madd in Tajweed: Types of Prolongation and How Many Counts',
			'desc'  => 'The types of madd: natural (2 counts), connected, separated, necessary (6) and the madd of stopping, with examples.',
			'faq'   => array(
				array( 'What is Madd?', 'Madd means prolongation: stretching a long vowel (alif, waw or ya) for a set number of counts.' ),
				array( 'How many counts is natural madd?', 'Two counts.' ),
				array( 'What is the longest madd?', 'Madd Lazim (necessary madd) is held for 6 counts.' ),
			),
			'body'  => '<p><strong>Madd</strong> means prolongation. The letters of madd are alif, waw and ya, when they follow a matching vowel. One count is about the time it takes to close or open a finger at a steady pace.</p>'
				. '<table><thead><tr><th>Type</th><th>When</th><th>Length</th><th>Example</th></tr></thead><tbody>'
				. self::row( array( 'Madd Asli (natural)', 'No hamzah or sukoon after the madd letter', '2 counts', self::ar( 'قَالَ' ) ) )
				. self::row( array( 'Madd Muttasil (connected)', 'Hamzah after the madd letter in the same word', '4 to 5 counts (obligatory)', self::ar( 'جَاءَ' ) . ' ' . self::ar( 'السَّمَاءِ' ) ) )
				. self::row( array( 'Madd Munfasil (separated)', 'Madd letter ends a word, hamzah begins the next', 'Permissible: varies by narration', self::ar( 'بِمَا أُنزِلَ' ) ) )
				. self::row( array( 'Madd Lazim (necessary)', 'Madd letter followed by a permanent sukoon or shaddah', '6 counts', self::ar( 'الضَّالِّينَ' ) . ' ' . self::ar( 'الْحَاقَّةُ' ) ) )
				. self::row( array( 'Madd Arid (when stopping)', 'You stop on a word whose last letter follows a madd letter', '2, 4 or 6 counts', self::ar( 'الرَّحِيمِ' ) . ' ' . self::ar( 'نَسْتَعِينُ' ) ) )
				. '</tbody></table><p>Lengths for Madd Munfasil differ between recitation narrations, so follow your teacher on this one.</p>',
			'codes' => 'n,p,m,o',
		);

		$specs['sun-and-moon-letters'] = array(
			'title' => 'Sun and Moon Letters in Arabic: Lam Shamsiyyah and Qamariyyah',
			'desc'  => 'How to read "al-" before the 14 sun letters and the 14 moon letters, with the full lists and examples.',
			'faq'   => array(
				array( 'What are the sun letters?', 'Fourteen letters before which the lam of "al-" is not pronounced and the letter is doubled.' ),
				array( 'What are the moon letters?', 'The other fourteen. Before them the lam of "al-" is pronounced clearly.' ),
			),
			'body'  => '<p>The definite article <em>al-</em> is read two ways depending on the next letter.</p>'
				. '<h2>Sun letters (Lam Shamsiyyah)</h2><p>' . $j( $L['sun'] ) . '</p><p>The lam is <strong>not pronounced</strong> and the next letter is doubled: ' . self::ar( 'الشَّمْس' ) . ' and ' . self::ar( 'النَّاس' ) . '.</p>'
				. '<h2>Moon letters (Lam Qamariyyah)</h2><p>' . $j( $L['moon'] ) . '</p><p>The lam <strong>is pronounced</strong> clearly: ' . self::ar( 'الْقَمَر' ) . ' and ' . self::ar( 'الْكِتَاب' ) . '.</p>',
			'codes' => 'l',
		);

		$specs['makharij-al-huruf'] = array(
			'title' => 'Makharij Al-Huruf: Where Each Arabic Letter Is Pronounced',
			'desc'  => 'The five general areas and the 17 specific points of articulation of the Arabic letters in tajweed, with a clear table.',
			'faq'   => array(
				array( 'What are Makharij?', 'Makharij (singular makhraj) are the points of articulation: the places in the throat, mouth, lips and nasal passage where each letter is produced.' ),
				array( 'How many makharij are there?', 'Most teachers count five general areas and seventeen specific points. Some scholars count fewer, grouping them differently.' ),
			),
			'body'  => '<p>Every Arabic letter has a place where it is produced, called its <strong>makhraj</strong>. Five general areas, seventeen specific points (a common way of counting; some teachers group them differently).</p>'
				. '<table><thead><tr><th>Area</th><th>Specific point</th><th>Letters</th></tr></thead><tbody>'
				. self::row( array( 'Al-Jawf (open space of mouth and throat)', 'Open space', $j( array( 'ا', 'و', 'ي' ) ) . ' (as long vowels)' ) )
				. self::row( array( 'Al-Halq (throat)', 'Deepest part', $j( array( 'ء', 'ه' ) ) ) )
				. self::row( array( '', 'Middle', $j( array( 'ع', 'ح' ) ) ) )
				. self::row( array( '', 'Nearest to the mouth', $j( array( 'غ', 'خ' ) ) ) )
				. self::row( array( 'Al-Lisan (tongue)', 'Back of the tongue with the soft palate', self::ar( 'ق' ) ) )
				. self::row( array( '', 'Back of the tongue, a little forward of qaf', self::ar( 'ك' ) ) )
				. self::row( array( '', 'Middle of the tongue', $j( array( 'ج', 'ش', 'ي' ) ) ) )
				. self::row( array( '', 'Side of the tongue', self::ar( 'ض' ) ) )
				. self::row( array( '', 'Edge of the tongue (front)', self::ar( 'ل' ) ) )
				. self::row( array( '', 'Tip of the tongue', self::ar( 'ن' ) ) )
				. self::row( array( '', 'Tip of the tongue, slightly further back', self::ar( 'ر' ) ) )
				. self::row( array( '', 'Tip of the tongue with the base of the upper front teeth', $j( array( 'ط', 'د', 'ت' ) ) ) )
				. self::row( array( '', 'Tip of the tongue near the lower front teeth', $j( array( 'ص', 'ز', 'س' ) ) ) )
				. self::row( array( '', 'Tip of the tongue with the edges of the upper front teeth', $j( array( 'ظ', 'ذ', 'ث' ) ) ) )
				. self::row( array( 'Ash-Shafatayn (lips)', 'Inner lower lip with the edges of the upper front teeth', self::ar( 'ف' ) ) )
				. self::row( array( '', 'Both lips', $j( array( 'ب', 'م', 'و' ) ) ) )
				. self::row( array( 'Al-Khayshum (nasal passage)', 'Nasal passage', 'The ghunnah of noon and meem' ) )
				. '</tbody></table><p>Reading a table is only a start: your ear and a teacher\'s correction are what fix pronunciation.</p>',
			'codes' => '',
		);

		return $specs;
	}

	/** Glossary: term => definition. */
	public static function glossary() {
		return array(
			array( 'Tajweed', 'The science of reciting the Quran correctly, giving every letter its proper sound, length and qualities.' ),
			array( 'Makharij', 'The points of articulation: where in the throat, tongue, lips or nasal passage each letter is produced.' ),
			array( 'Sifat', 'The qualities (attributes) of a letter, such as heaviness, lightness or echo.' ),
			array( 'Harakat', 'The short vowel marks: fatha, kasra and damma.' ),
			array( 'Fatha', 'A short "a" vowel, written as a small diagonal line above the letter.' ),
			array( 'Kasra', 'A short "i" vowel, written below the letter.' ),
			array( 'Damma', 'A short "u" vowel, written as a small waw-like mark above the letter.' ),
			array( 'Sukoon', 'A sign showing the letter has no vowel.' ),
			array( 'Shaddah', 'A sign showing the letter is doubled.' ),
			array( 'Tanween', 'A doubled vowel at the end of a noun that adds an "n" sound.' ),
			array( 'Noon sakinah', 'A noon carrying a sukoon, to which the four noon rules apply (together with tanween).' ),
			array( 'Meem sakinah', 'A meem carrying a sukoon, which has three rules: Ikhfa Shafawi, Idgham Shafawi and Izhar Shafawi.' ),
			array( 'Izhar', 'Clarity: pronouncing noon sakinah or tanween clearly before the six throat letters.' ),
			array( 'Idgham', 'Merging: blending noon sakinah or tanween into one of the six letters of "yarmaloon".' ),
			array( 'Iqlab', 'Changing: noon sakinah or tanween turning into a hidden meem before the letter ba.' ),
			array( 'Ikhfa', 'Hiding: pronouncing noon sakinah or tanween with ghunnah before 15 letters.' ),
			array( 'Ghunnah', 'The nasal sound of noon and meem, held for about 2 counts.' ),
			array( 'Qalqalah', 'An echo on the letters qaf, ta (heavy), ba, jim and dal when they carry a sukoon.' ),
			array( 'Madd', 'Prolongation: stretching a long vowel (alif, waw or ya) for a set number of counts.' ),
			array( 'Madd Asli', 'The natural madd, held for 2 counts.' ),
			array( 'Madd Lazim', 'The necessary madd: a madd letter followed by a permanent sukoon or shaddah, held for 6 counts.' ),
			array( 'Lam Shamsiyyah', 'The lam of "al-" before a sun letter: not pronounced, the next letter is doubled.' ),
			array( 'Lam Qamariyyah', 'The lam of "al-" before a moon letter: pronounced clearly.' ),
			array( 'Hamzat al-wasl', 'A connecting hamza: pronounced when you begin with it, dropped when you read on.' ),
			array( 'Tafkheem', 'Making a letter heavy (full-mouthed).' ),
			array( 'Tarqeeq', 'Making a letter light (thin).' ),
			array( 'Waqf', 'Stopping or pausing while reciting.' ),
			array( 'Ibtida', 'Starting or resuming recitation after a stop.' ),
			array( 'Ijazah', 'A teacher\'s authorised permission, linked through a chain of teachers back to the Prophet, to teach the Quran or a recitation.' ),
			array( 'Qira\'at', 'The authentic recitation traditions of the Quran; the most widely read in the world today is Hafs from Asim.' ),
		);
	}

	public static function glossary_spec() {
		$body = '<p>A short glossary of tajweed terms, in plain English.</p><dl class="tjq-gl">';
		foreach ( self::glossary() as $g ) {
			$body .= '<dt id="' . esc_attr( sanitize_title( $g[0] ) ) . '"><strong>' . esc_html( $g[0] ) . '</strong></dt><dd>' . esc_html( $g[1] ) . '</dd>';
		}
		$body .= '</dl>';
		return array(
			'title' => 'Tajweed Glossary: Key Terms Explained Simply',
			'desc'  => 'A plain-English glossary of tajweed terms: makharij, ghunnah, qalqalah, idgham, ikhfa, madd, waqf and more.',
			'faq'   => array(),
			'body'  => $body . '<p>Learn the rules in context: <a href="{{url:tajweed-rules}}">tajweed rules explained</a>.</p>',
			'codes' => '',
		);
	}

	public static function reader_spec() {
		return array(
			'title' => 'Quran with Tajweed Colors: Free Color-Coded Quran Online',
			'desc'  => 'Read the Quran with colour-coded tajweed rules and English translation. Choose any surah, see a colour guide and learn each rule.',
			'faq'   => array(
				array( 'What do the colours mean?', 'Each colour marks a tajweed rule, such as ghunnah, qalqalah, madd or ikhfa. A colour guide appears above every surah.' ),
				array( 'Is the colour-coded Quran enough to learn tajweed?', 'It is a great way to notice rules while reading, but pronunciation is learned by listening and being corrected by a qualified teacher.' ),
			),
			'body'  => '<p>Pick a surah to read it with tajweed colours. Open the colour guide, switch colours or the translation on and off, and enlarge the text.</p>[tajweedly_mushaf]',
			'codes' => '',
		);
	}

	public static function ramadan_spec() {
		return array(
			'title' => 'Ramadan Tajweed Challenge: 14 Free Daily Lessons by Email',
			'desc'  => 'Join the free Ramadan Tajweed challenge: one five-minute lesson a day by email to improve your recitation before and during Ramadan.',
			'faq'   => array(
				array( 'Is the Ramadan challenge free?', 'Yes. You receive one short lesson a day for 14 days, free of charge.' ),
				array( 'How long does each lesson take?', 'About five minutes, with one small practice task.' ),
			),
			'body'  => '<p>Make this Ramadan the one where your recitation improves. Each day you get a short lesson and a practice task, straight to your inbox.</p>[tajweedly_challenge]'
				. '<h2>What you will cover</h2><ul><li>Makharij and short vowels</li><li>Noon sakinah and tanween rules</li><li>Meem sakinah, qalqalah and madd</li><li>Stopping, and a before-and-after recording</li></ul>'
				. '<p>Prefer to check your level first? <a href="{{url:tajweed-test}}">Take the free test</a>.</p>',
			'codes' => '',
		);
	}

	/** Specs for one surah page. */
	public static function surah_spec( $n ) {
		$all  = TJQ_Mushaf::surahs();
		$name = $all[ $n ][0];
		$cnt  = $all[ $n ][1];
		$prev = isset( $all[ $n - 1 ] ) ? '<a href="{{url:' . TJQ_Mushaf::surah_slug( $n - 1 ) . '}}">Previous: Surah ' . esc_html( $all[ $n - 1 ][0] ) . '</a>' : '';
		$next = isset( $all[ $n + 1 ] ) ? '<a href="{{url:' . TJQ_Mushaf::surah_slug( $n + 1 ) . '}}">Next: Surah ' . esc_html( $all[ $n + 1 ][0] ) . '</a>' : '';
		$nav  = trim( $prev . ( $prev && $next ? ' &middot; ' : '' ) . $next );
		return array(
			'slug'  => TJQ_Mushaf::surah_slug( $n ),
			'title' => sprintf( 'Surah %s with Tajweed Colors | Color-Coded Quran', $name ),
			'desc'  => sprintf( 'Read Surah %s (%d ayat, surah %d of 114) with colour-coded tajweed rules, English translation, a colour guide and a count of every rule in the surah.', $name, $cnt, $n ),
			'body'  => sprintf( '<p>Surah %1$s is surah number %2$d of 114 and has %3$d ayat. Below it is shown with tajweed colours: each colour marks a rule such as ghunnah, qalqalah, ikhfa or madd, and the colour guide explains all of them. Use the buttons to change the text size, switch to a dark theme or hide the translation.</p>[tajweedly_mushaf surah="%2$d"]%4$s<p>More: <a href="{{url:quran-with-tajweed-colors}}">all surahs</a> and <a href="{{url:tajweed-rules}}">tajweed rules explained</a>.</p>', esc_html( $name ), $n, $cnt, $nav ? '<p>' . $nav . '</p>' : '' ),
			'faq'   => array(
				array( sprintf( 'How many ayat are in Surah %s?', $name ), sprintf( 'Surah %s has %d ayat.', $name, $cnt ) ),
				array( 'What do the colours mean?', 'Each colour marks a tajweed rule, for example red marks qalqalah and orange marks ghunnah. Open the colour guide above the text for every colour.' ),
				array( 'Can I learn tajweed from colours alone?', 'Colours are a learning aid. Recitation should be learned from a qualified teacher and checked against a printed mushaf.' ),
			),
			'meta'  => array( '_tjq_surah' => $n ),
		);
	}

	/** Surahs created by default: Al-Fatihah, Al-Kahf, Ya-Sin, Ar-Rahman, Al-Waqiah, Al-Mulk and all of Juz Amma. */
	public static function default_surahs() {
		return array_merge( array( 1, 18, 36, 55, 56, 67 ), range( 78, 114 ) );
	}
}

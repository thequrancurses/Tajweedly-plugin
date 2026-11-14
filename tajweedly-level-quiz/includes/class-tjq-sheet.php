<?php
defined( 'ABSPATH' ) || exit;

/**
 * Free printable cheat sheets per level (the browser's "Save as PDF" makes the PDF).
 * Link format: /?tjq_sheet=ID&key=HMAC. The key is per lead, so sheets are only reachable by people who got the link.
 */
class TJQ_Sheet {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	private static function key( $id ) {
		return hash_hmac( 'sha256', 'sheet|' . (int) $id, wp_salt( 'auth' ) );
	}

	public static function url( $row ) {
		return add_query_arg(
			array(
				'tjq_sheet' => (int) $row->id,
				'key'       => self::key( $row->id ),
			),
			home_url( '/' )
		);
	}

	public static function level_for_row( $row ) {
		if ( in_array( $row->level, array( 'beginner', 'intermediate', 'advanced' ), true ) ) {
			return $row->level;
		}
		return 'challenge' === $row->level ? 'intermediate' : 'beginner';
	}

	public static function maybe_render() {
		if ( empty( $_GET['tjq_sheet'] ) || empty( $_GET['key'] ) ) {
			return;
		}
		$id = (int) $_GET['tjq_sheet'];
		if ( ! hash_equals( self::key( $id ), sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) ) {
			wp_die( 'Invalid link', 'Invalid link', array( 'response' => 403 ) );
		}
		$row = TJQ_DB::get( $id );
		if ( ! $row ) {
			wp_die( 'Not found', 'Not found', array( 'response' => 404 ) );
		}
		$level = self::level_for_row( $row );
		$s     = TJQ_Settings::all();
		$tests = TJQ_Questions::levels();
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $tests[ $level ]['label'] . ' Tajweed cheat sheet - ' . $s['brand_name'] ); ?></title>
<?php if ( $s['font_url'] ) : ?>
<link rel="stylesheet" href="<?php echo esc_url( $s['font_url'] ); ?>">
<?php endif; ?>
<style>
:root{--p:<?php echo esc_attr( $s['primary_color'] ); ?>;--a:<?php echo esc_attr( $s['accent_color'] ); ?>}
*{box-sizing:border-box}
body{font-family:<?php echo $s['font_family'] ? esc_attr( $s['font_family'] ) : 'Arial,Helvetica,sans-serif'; ?>;color:#1f2937;max-width:820px;margin:0 auto;padding:24px;line-height:1.55}
header{display:flex;justify-content:space-between;align-items:center;border-bottom:4px solid var(--p);padding-bottom:10px;margin-bottom:18px}
header img{max-height:42px}
h1{color:var(--p);margin:0;font-size:1.6em}
h2{color:var(--p);margin:22px 0 8px;font-size:1.2em;border-inline-start:5px solid var(--a);padding-inline-start:10px}
table{border-collapse:collapse;width:100%;margin:8px 0;font-size:.95em}
th,td{border:1px solid #d1d5db;padding:7px 9px;text-align:start;vertical-align:top}
th{background:#f3f4f6}
.ar{font-family:"Amiri","Scheherazade New","Traditional Arabic",serif;font-size:1.5em;direction:rtl;unicode-bidi:isolate;line-height:1.6}
.big{font-size:1.9em;letter-spacing:.2em}
.tip{background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;padding:10px 14px;margin:12px 0}
.btn{background:var(--p);color:#fff;border:0;border-radius:8px;padding:10px 18px;font-size:1em;cursor:pointer}
footer{margin-top:26px;border-top:1px solid #e5e7eb;padding-top:10px;font-size:.9em;color:#6b7280}
@media print{.noprint{display:none}body{padding:0}h2{break-after:avoid}table,.tip{break-inside:avoid}}
</style>
</head>
<body>
<header>
	<div>
		<h1><?php echo esc_html( $tests[ $level ]['label'] ); ?> Tajweed cheat sheet</h1>
		<div><?php echo esc_html( $s['brand_name'] ); ?></div>
	</div>
	<?php if ( $s['logo_url'] ) : ?><img src="<?php echo esc_url( $s['logo_url'] ); ?>" alt=""><?php endif; ?>
</header>
<p class="noprint"><button class="btn" onclick="window.print()">Print / Save as PDF</button></p>
		<?php
		echo self::body( $level ); // phpcs:ignore WordPress.Security.EscapeOutput -- static trusted markup.
		?>
<div class="tip">Rules are learned by listening. Book a free trial lesson and have a qualified teacher correct your recitation live<?php echo $s['trial_url'] ? ': ' . esc_html( $s['trial_url'] ) : ''; ?>.</div>
<footer><?php echo esc_html( $s['brand_name'] ); ?> &middot; Summary for revision. It does not replace learning from a qualified teacher.</footer>
</body>
</html>
		<?php
		exit;
	}

	private static function ar( $text, $class = '' ) {
		return '<span class="ar ' . $class . '" lang="ar" dir="rtl">' . $text . '</span>';
	}

	private static function body( $level ) {
		$a = array( __CLASS__, 'ar' );
		ob_start();
		if ( 'beginner' === $level ) :
			?>
<h2>1. The 28 letters</h2>
<p><?php echo self::ar( 'ا ب ت ث ج ح خ د ذ ر ز س ش ص ض ط ظ ع غ ف ق ك ل م ن ه و ي', 'big' ); ?></p>
<h2>2. Vowels and signs (shown on ب)</h2>
<table>
<tr><th>Sign</th><th>Name</th><th>Meaning</th></tr>
<tr><td><?php echo self::ar( 'بَ' ); ?></td><td>Fatha</td><td>short "a"</td></tr>
<tr><td><?php echo self::ar( 'بِ' ); ?></td><td>Kasra</td><td>short "i"</td></tr>
<tr><td><?php echo self::ar( 'بُ' ); ?></td><td>Damma</td><td>short "u"</td></tr>
<tr><td><?php echo self::ar( 'بْ' ); ?></td><td>Sukoon</td><td>no vowel</td></tr>
<tr><td><?php echo self::ar( 'بّ' ); ?></td><td>Shaddah</td><td>the letter is doubled</td></tr>
<tr><td><?php echo self::ar( 'بً بٍ بٌ' ); ?></td><td>Tanween</td><td>doubled vowel at the end of a noun, adds an "n" sound</td></tr>
</table>
<h2>3. Letters of Madd (long vowels)</h2>
<p><?php echo self::ar( 'ا و ي', 'big' ); ?> A natural madd (Madd Asli) is held for <strong>2 counts</strong>.</p>
<h2>4. The five areas of articulation (Makharij)</h2>
<table>
<tr><th>Area</th><th>Letters / sound</th></tr>
<tr><td>Al-Jawf (the empty space of mouth and throat)</td><td>The long-vowel letters <?php echo self::ar( 'ا و ي' ); ?></td></tr>
<tr><td>Al-Halq (throat)</td><td><?php echo self::ar( 'ء ه ع ح غ خ' ); ?></td></tr>
<tr><td>Al-Lisan (tongue)</td><td>Most of the other letters</td></tr>
<tr><td>Ash-Shafatayn (lips)</td><td><?php echo self::ar( 'ب م و' ); ?> (and ف with the teeth)</td></tr>
<tr><td>Al-Khayshum (nasal passage)</td><td>The ghunnah (nasal sound)</td></tr>
</table>
<h2>5. Stopping (Waqf)</h2>
<p>Stopping at the end of a word normally gives the last letter a sukoon and you take a breath.</p>
			<?php
		elseif ( 'intermediate' === $level ) :
			?>
<h2>1. Noon Sakinah and Tanween</h2>
<table>
<tr><th>Rule</th><th>Letters after the noon</th><th>How</th></tr>
<tr><td>Izhar (clear)</td><td><?php echo self::ar( 'ء ه ع ح غ خ' ); ?> (6 throat letters)</td><td>Pronounce the noon clearly, no ghunnah</td></tr>
<tr><td>Idgham with ghunnah</td><td><?php echo self::ar( 'ي ن م و' ); ?> (يَنْمُو)</td><td>Merge, keep the ghunnah</td></tr>
<tr><td>Idgham without ghunnah</td><td><?php echo self::ar( 'ل ر' ); ?></td><td>Merge completely</td></tr>
<tr><td>Iqlab (switch)</td><td><?php echo self::ar( 'ب' ); ?></td><td>Noon becomes a hidden meem with ghunnah</td></tr>
<tr><td>Ikhfa (hide)</td><td><?php echo self::ar( 'ص ذ ث ك ج ش ق س د ط ز ف ت ض ظ' ); ?> (15 letters)</td><td>Hide the noon with ghunnah</td></tr>
</table>
<h2>2. Meem Sakinah</h2>
<table>
<tr><th>Rule</th><th>After the meem</th></tr>
<tr><td>Ikhfa Shafawi</td><td><?php echo self::ar( 'ب' ); ?></td></tr>
<tr><td>Idgham Shafawi</td><td><?php echo self::ar( 'م' ); ?></td></tr>
<tr><td>Izhar Shafawi</td><td>Every other letter</td></tr>
</table>
<h2>3. Ghunnah and Madd</h2>
<p>Ghunnah is held for about <strong>2 counts</strong>. Madd Asli (natural madd) is <strong>2 counts</strong>.</p>
<h2>4. Qalqalah</h2>
<p><?php echo self::ar( 'ق ط ب ج د', 'big' ); ?> (<?php echo self::ar( 'قُطْبُ جَدٍّ' ); ?>). Echo the letter when it has a sukoon, without adding a vowel.</p>
			<?php
		else :
			?>
<h2>1. Types of Madd</h2>
<table>
<tr><th>Type</th><th>When</th><th>Length</th></tr>
<tr><td>Madd Asli</td><td>Natural: no hamzah or sukoon after the madd letter</td><td>2 counts</td></tr>
<tr><td>Madd Muttasil</td><td>Hamzah after the madd letter in the same word</td><td>Obligatory stretch, commonly 4 to 5 counts</td></tr>
<tr><td>Madd Munfasil</td><td>Madd letter ends a word, hamzah starts the next</td><td>Permissible: 2, 4 or 5 counts depending on the narration</td></tr>
<tr><td>Madd Lazim</td><td>Madd letter followed by a permanent sukoon or shaddah</td><td>6 counts</td></tr>
</table>
<h2>2. Heavy letters (Isti'la)</h2>
<p><?php echo self::ar( 'خ ص ض غ ط ق ظ', 'big' ); ?> (<?php echo self::ar( 'خُصَّ ضَغْطٍ قِظْ' ); ?>). Keep the back of the tongue raised.</p>
<h2>3. Ra and Lam of "Allah"</h2>
<table>
<tr><th>Case</th><th>Pronunciation</th></tr>
<tr><td>Ra with fatha or damma</td><td>Heavy</td></tr>
<tr><td>Ra with kasra</td><td>Usually light</td></tr>
<tr><td>Lam of "Allah" after fatha or damma</td><td>Heavy</td></tr>
<tr><td>Lam of "Allah" after kasra</td><td>Light</td></tr>
</table>
<h2>4. Ghunnah</h2>
<p>The fullest ghunnah is on a <strong>Noon or Meem with shaddah</strong> (<?php echo self::ar( 'نَّ مَّ' ); ?>).</p>
<h2>5. Qalqalah and Waqf</h2>
<p>Qalqalah is strongest when you <strong>stop</strong> on the letter. When stopping on a Ta Marbuta (<?php echo self::ar( 'ة' ); ?>) it is pronounced like a soft <strong>Ha</strong>.</p>
			<?php
		endif;
		return ob_get_clean();
	}
}

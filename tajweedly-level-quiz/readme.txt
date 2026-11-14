=== Tajweedly Level Quiz ===
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later

Tajweed level tests (Beginner, Intermediate, Advanced, Kids readiness) that recommend a course, collect consented email leads and follow up automatically.

== Install ==
1. Upload the zip in Plugins > Add New > Upload Plugin, then activate.
2. Go to Tajweed Quiz > Settings, press "Detect from homepage" to copy your site's colours/logo, and add your free-trial booking URL, course URLs, privacy policy URL and WhatsApp number.
3. Create a page and add the shortcode [tajweedly_quiz].

== Features ==
* Tests: Beginner / Intermediate / Advanced (10 questions each) + parents' "Is my child ready?" test
* Server-side scoring (answers never reach the browser); add your own questions, including audio questions
* Result + recommended next step; downloadable/shareable result card (WhatsApp, Instagram, etc.)
* Email capture with explicit, recorded consent (GDPR / UK PECR friendly), one-click unsubscribe
* Result email, admin notification, webhook (Zapier / Make / n8n / Brevo / Mailchimp)
* Automatic follow-up sequences (4 emails per test, 14-day challenge, "finish your test" reminder). Stops when lead status is Trial / Paid / Lost
* Inline Calendly booking, pre-filled with name, email and result
* Free printable cheat sheet per level (Save as PDF), per-lead private link
* Referral links with tracking ("you both get a free lesson")
* Save progress: browser auto-resume + "email me a link to finish later"
* A/B test for the lead-form button, with results on the Dashboard
* Blog: CTA box at the end of every post, optional one-time popup, [tajweedly_cta] shortcode
* One-click creation of 6 SEO landing pages (drafts) with meta descriptions, FAQ and JSON-LD schema
* Interface languages: English, Arabic (RTL), Urdu (RTL), French
* Leads table: status (New / Contacted / Trial booked / Paid / Lost), search, filters, CSV export, delete
* UTM tracking and funnel stats; dataLayer / gtag / Meta Pixel events (tjq_start, tjq_complete, tjq_lead, tjq_share, tjq_challenge...)
* WordPress personal data export / erase tools, honeypot, rate limiting
* Brand detection from the homepage; inherits theme font

== Shortcodes ==
[tajweedly_quiz]  [tajweedly_quiz level="beginner|intermediate|advanced|kids"]  [tajweedly_quiz goal_step="no"]
[tajweedly_challenge]   14-day email challenge sign-up
[tajweedly_cta text="..." level="..."]   static link box to the quiz page

== Developer hooks ==
* filter tjq_questions - edit or extend the question bank
* filter tjq_goals - edit the goal options
* filter tjq_level_info - change level text / thresholds output
* action tjq_lead_captured - runs when a lead is saved, receives the row

== Changelog ==
= 1.3.1 =
* SEO: surah pages now have unique intro, FAQ, previous/next links; every learning page gets a "Keep learning" link block; BreadcrumbList + datePublished schema; canonical and Open Graph tags when no SEO plugin is active; surah pages stay noindex until their text is downloaded.

= 1.3.0 =
* Colour-coded Quran reader ([tajweedly_mushaf surah="..."]): official tajweed colours, rule counts, colour guide, themes, font size, translation toggle. Text is downloaded from AlQuran Cloud and cached; nothing is typed from memory.
* 14 rule/glossary/reader/Ramadan pages and 43 surah pages created as DRAFTS from Quran & pages.
* Embeddable quiz (?tjq_embed=LEVEL) with backlink snippet, [tajweedly_tip] tip of the day, Insights (most-missed questions, press summary from real data only).
* Review all generated content with a qualified teacher before publishing.

= 1.2.0 =
* Kids readiness test, email follow-up sequences, 14-day challenge, inline Calendly, cheat sheets, referrals, share card, save progress, A/B test, blog CTA/popup, SEO landing pages + schema, lead statuses, audio/custom questions, Arabic/Urdu/French interface.

= 1.1.0 =
* Three separate level tests (Beginner, Intermediate, Advanced), 10 questions each; [tajweedly_quiz level="beginner"] for dedicated landing pages.

= 1.0.0 =
* First release.

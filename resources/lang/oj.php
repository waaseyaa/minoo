<?php

declare(strict_types=1);

// ============================================================================
// Anishinaabemowin (Ojibwe) translations for Minoo
// ============================================================================
//
// IMPORTANT — DRAFT: These translations are SUGGESTIONS sourced from the
// Ojibwe People's Dictionary (ojibwe.lib.umn.edu) containing 21,721 entries.
//
// DO NOT USE IN PRODUCTION without verification by a fluent Anishinaabemowin
// speaker. Dictionary lemmas (citation forms) may not be appropriate as UI
// translations without proper context, conjugation, and community review.
//
// Translation approach:
//   - Single-word/short-phrase UI elements (nav, buttons, labels): best
//     dictionary match applied, marked with // dict: source
//   - Longer phrases, sentences, paragraphs: left as '' for human translation
//   - Each translated value has a // dict: comment noting the source word
//
// Verified dictionary matches used:
//   endaad          — "h/ home; h/ house"
//   oodena          — "town" (plural: oodenawinan)
//   anishinaabe(g)  — "person/people"
//   gikinoo'amaadiwin — "teaching, education"
//   maawanji'idiwag — "they come together, meet" (noun: maawanji'idiwin)
//   gichi-aya'aa    — "an adult, an elder"
//   wiidookaage     — "s/he helps people" (noun: wiidookaagewin)
//   andone'         — "go look for, search for, seek"
//   anokiiwin       — "work, activity"
//   zaaga'am        — "s/he goes out, exits"
//   biindige        — "enter, go inside"
//   miigwech        — "thanks!"
//   boozhoo'        — "say hello to h/"
//   dibaajimowin    — "a story; a narrative; a report"
//   ganawendan      — "take care of, protect, keep it"
//   izhinikaazowin  — "s/he is named a certain way" (name)
//   aki             — "land, earth, country"
//   ogimaa          — "a chief, a boss"
//   inwewin         — "a language, a dialect"
//   Anishinaabemowin — "Ojibwe language"
//   ikidowin        — "a word"
//   gaagiigido      — "s/he speaks, talks"
//   bizindaw        — "listen to h/"
//   andawaabandan   — "look for, search for it"
//   aanjitoon       — "change it"
//   eya'            — "yes"
//   gaawiin         — "no, not"
//
// Source: Ojibwe People's Dictionary, University of Minnesota
//         https://ojibwe.lib.umn.edu
// ============================================================================
return [

    // Base layout
    'base.skip_link' => "Gwaashkwanin gichi-mazina'iganing", // dict: gwaashkwani: "jump"; gichi-: "main"; mazina'igan: "page/document"
    'base.home_label' => 'Minoo — endaayan', // dict: endaayan: "where you live/home"
    'base.menu' => "Mazina'igan", // dict: mazina'igan: "a page, a list" (avoiding English loanword "Meni")
    'base.nav_main' => 'Gichi', // dict: gichi-: "great, main"

    // Navigation
    'nav.communities' => 'Oodenawinan', // dict: oodena: "town"; -winan plural
    'nav.people' => 'Anishinaabeg', // dict: anishinaabeg: plural of anishinaabe
    'nav.teachings' => 'Gikinoo\'amaadiwinan', // dict: gikinoo'amaadiwin: "teaching, education"
    'nav.events' => 'Maawanji\'idiwinan', // dict: maawanji'idiwag: "they come together, meet"
    'nav.oral_histories' => 'Gdibaajmowinaanin', // dict: dibaajmowin: "story, narrative"; g- (our) + -aanin plural — "Our Stories"
    'nav.programs' => 'Anokiiwinan', // dict: anokii: "s/he works"; -winan nominal plural
    'nav.elder_program' => 'Gichi-aya\'aa Wiidookaagewin', // dict: gichi-aya'aa: "an elder" + wiidookaagewin: "help, assistance"
    'nav.request_help' => '', // TODO: Ojibwe translation needed
    'nav.volunteer' => 'Wiidookaage', // dict: wiidookaage: "s/he helps people"
    'nav.search' => 'Andone\'igen', // dict: andone': "go look for, search for, seek"
    'nav.dashboard' => 'Anokiiwin', // dict: anokiiwin: work, activity
    'nav.my_dashboard' => 'Nindanokiiwin', // dict: nind- (my) + anokiiwin (work/activity)
    'nav.account' => 'Nindizhinikaazowin', // dict: nind- (my) + izhinikaazowin (name/identity) — "my account"
    'nav.logout' => 'Zaaga\'an', // dict: zaaga'am: "s/he goes out, exits"
    'nav.login' => 'Biindigen', // dict: biindige: "enter, go inside"
    'nav.messages' => 'Mazina\'iganan', // dict: mazina'igan: letter/message/document

    // Share toolbar (EN until community review)
    'share.toolbar_aria' => 'Share this page',
    'share.page_heading' => 'Share',
    'share.facebook' => 'Facebook',
    'share.twitter' => 'X',
    'share.linkedin' => 'LinkedIn',
    'share.email' => 'Email',
    'share.copy_link' => 'Copy link',
    'share.copy_success' => 'Link copied to clipboard.',
    'share.copy_fail' => 'Could not copy automatically — select the address in your browser bar.',
    'share.native' => 'Share using device',

    // Media carousel (EN until reused on Anishinaabemowin pages)
    'media_carousel.toolbar_aria' => 'Carousel controls',
    'media_carousel.prev' => 'Previous slide',
    'media_carousel.next' => 'Next slide',
    'media_carousel.roledescription' => 'carousel',
    'media_carousel.view_larger' => 'View larger',
    'media_carousel.tabs_aria' => 'Choose slide',
    'media_carousel.slide_n' => 'Slide {n} of {total}',
    'media_carousel.lightbox_title' => 'Enlarged image',
    'media_carousel.lightbox_title_pattern' => 'Image __MC_N__ of __MC_T__',
    'media_carousel.close' => 'Close',

    // Footer
    'footer.tagline' => "Bimaadiziwin oodena-mazina'igan", // "A living community-map" — natural Ojibwe word order (modifier before noun)
    'footer.copyright' => 'Minoo',
    'footer.license' => "Oodena-mazina'iganan aabajichigaadewan <a href=\"https://creativecommons.org/licenses/by-nc-sa/4.0/\" rel=\"external noopener\">CC BY-NC-SA 4.0</a> inaakoniganing.", // dict: aabajichigaade: "it is used/shared"; inaakonigan: "agreement/license"
    'footer.nav_label' => 'Inaakonige', // dict: inaakonige: "rules, decisions, legal"
    'footer.about' => 'Dibaajimowin', // dict: dibaajimowin: "a story; a narrative; a report"
    'footer.privacy' => 'Ganawendamowin', // dict: ganawendan: "protect, keep" + -mowin nominal — "protection/privacy"
    'footer.terms' => 'Inaakonigan', // dict: inaakonigan: "a rule, a decision" (noun form, distinct from footer.nav_label verb)
    'footer.accessibility' => 'Odaapinamowin', // dict: odaapinan: "accept, receive" + -mowin — "accessibility/receptiveness"
    'footer.data_sovereignty' => 'Mazina\'igan-ogimaawiwin', // dict: mazina'igan: "document/data" + ogimaawiwin: "governance/sovereignty"

    // Location bar
    'location.set' => 'Ozhitoon gidoodena', // dict: ozhitoon: "make/set it" + gid-: "your" + oodena: "town"
    'location.near' => 'Besho {community}', // dict: besho: "near, nearby" (from beshowad: "it is near")
    'location.change' => 'Aanjitoon', // dict: aanjitoon: "change it"
    'location.search_label' => 'Andawaabandan oodenawinan', // dict: andawaabandan: "look for, search for it"
    'location.search_placeholder' => 'Andawaabandan oodenawinan…', // dict: andawaabandan: "look for, search for it"
    'location.noscript' => 'JavaScript izhi-aabajichigaade oodena-andone\'iganing.', // "JavaScript is used for location searching."
    'location.detecting' => "Nandawaabandamang gidoodena\u{2026}", // dict: nandawaabandan: "look for it" — "Looking for your location…"
    'location.error' => "Gii-wanichige — gaawiin gashkitoosiin ji-mikamang.", // dict: wanichige: "make a mistake"; gaawiin gashkitoosiin: "unable to"

    // Language switcher
    'language_switcher.label' => 'Inwewin', // dict: inwewin: "a language, a dialect"

    // Chat
    'chat.toggle_label' => '',
    'chat.title' => '',
    'chat.close_label' => '',
    'chat.initial_message' => '',
    'chat.input_label' => '',
    'chat.input_placeholder' => '',
    'chat.send_button' => 'Izhinizha\'an', // dict: izhinizha'an: send it
    'chat.disclaimer' => '',
    'chat.thinking' => '',
    'chat.error' => '',

    // Messaging
    'messages.title' => 'Mazina\'iganan',
    'messages.heading' => 'Mazina\'iganan',
    'messages.subheading' => '',
    'messages.inbox' => '',
    'messages.select_thread' => '',
    'messages.untitled_thread' => '',
    'messages.no_messages_yet' => '',
    'messages.empty_inbox' => '',
    'messages.auth_required_title' => '',
    'messages.auth_required_body' => '',
    'messages.compose_label' => '',
    'messages.compose_placeholder' => '',
    'messages.send' => 'Izhinizha\'an', // dict: send it
    'messages.load_error' => '',

    // Home page
    'page.title' => "Gikinoo'amaadiwin Mazina'igan O'ow Akiing",
    'page.nearby_heading' => 'Besho {community}', // dict: besho: "near, nearby"
    'page.explore_north_shore' => 'Naanaagadawaabandan oodenawinan', // dict: naanaagadawaabandan: "examine, consider it"; oodenawinan: "towns/communities"
    'page.search_label' => "Andone'igen", // dict: andone': "go look for, search for, seek"
    'page.search_type' => "Andone'igen izhichigewin", // dict: andone': "search"; izhichigewin: "an activity"
    'page.search_all' => 'Kina gegoo', // dict: kina: "all"; gegoo: "something"
    'page.search_businesses' => 'Anokiiwinan', // dict: anokiiwin: "work, activity"
    'page.search_people' => 'Anishinaabeg', // dict: anishinaabe: "person" (plural)
    'page.search_events' => "Maawanji'idiwinan", // dict: maawanji'idiwag: "they come together, meet"
    'page.search_query' => "Andone'igen", // dict: andone': "search"
    'page.search_placeholder' => "Wegonen nandawaabandaman? m.t., manidoominensikaan, niimi'idiwin", // dict: nandawaabandan: "look for, search for it"
    'page.search_button' => "Andone'", // dict: andone': "search for, seek" — shorter for button fit
    'page.tabs_label' => 'Naanaagadawaabandan', // "browse/explore content"
    'page.tab_nearby' => 'Besho', // dict: beshowad: "it is near, nearby, close"
    'page.tab_posts' => 'Mazinibiiganan', // dict: mazinibiigan: "writing, a post" (plural)
    'page.tab_events' => "Maawanji'idiwinan", // dict: maawanji'idiwag: "they come together"
    'page.tab_people' => 'Anishinaabeg', // dict: anishinaabe: "person" (plural)
    'page.tab_groups' => 'Anokiiwinan', // dict: anokiiwin: "work, businesses"
    'page.type_business' => 'Anokiiwin', // dict: anokiiwin: "work, activity, business"
    'page.type_group' => 'Anokiiwin', // "Group/Organization"
    'page.type_event' => "Maawanji'idiwin", // dict: maawanji'idiwag: "gathering, event"
    'page.type_person' => 'Anishinaabe', // dict: anishinaabe: "a person"
    'page.empty_state' => "Maajii-anokiitaadiyang o'ow akiing. Gikenimaad ina anokiiwin, maawanji'idiwin, gemaa ogimaa ge-dazhindamang? Wiindamawishinaam.", // "We're just getting started in this area. Know a business, event, or leader we should include? Let us know."
    'page.communities_heading' => 'Oodenawinan', // dict: oodena: "town" (plural)
    'page.about_heading' => 'Aaniin Minoo?', // "What is Minoo?"
    'page.about_body' => "Nindozhitoomin igo ji-waabandamang Anishinaabe oodenawinan, anokiiwinan, miinawaa Gechi-inendaagozijig. Naanaagadawaabandan anokiiwinan, mikan maawanji'idiwinan, gemaa wiij'anishinaabemin gidakiimiing.", // "We're building a place where Indigenous communities, businesses, and Knowledge Keepers are visible. Browse businesses, find events, or connect with people in your area."
    'page.about_cta' => "Gikinoo'amaagowin nawaj", // dict: gikinoo'amaagowin: "learning"; nawaj: "more"
    'page.about_compact' => "Minoo odaakonan Anishinaabe oodenawinan.", // "Minoo connects Indigenous communities."

    // Events
    'events.title' => 'Maawanji\'idiwinan', // dict: maawanji'idiwag: "they come together, meet"
    'events.near_location' => 'Maawanji\'idiwinan Misi-minis-akiing', // "Events across Turtle Island"
    'events.subtitle' => "Niimi'idiwinan, anami'ewinan, miinawaa oodena-maawanji'idiwinan", // "Powwows, ceremonies, and community gatherings"
    'events.empty_heading' => "Maawanji'idiwinan gaawiin mikanziinaawaa", // "No events found"
    'events.empty_body' => "Maawanji'idiwinan bi-dagoshinomagadoon waabang.", // "Events are coming soon."
    'events.explore_button' => 'Naanaagadawaabandan oodenawinan', // "Explore communities"
    'events.detail_back' => "Maawanji'idiwinan", // "Events"
    'events.not_found' => "Maawanji'idiwin gaawiin mikanziin", // "Event not found"
    'events.not_found_message' => "Maawanji'idiwin gaa-nandawaabandaman gaawiin ayaasinoon.", // "The event you're looking for doesn't exist."
    'events.browse_all' => "Kakina maawanji'idiwinan", // "All events"
    'events.related_teachings' => "Gikinoo'amaadiwinan", // dict: gikinoo'amaadiwin: "teaching" (plural) — "Related Teachings"
    'events.people_connected' => "Anishinaabeg o'ow maawanji'idiwining", // "People at this gathering"
    'events.host_community' => 'Oodena', // dict: oodena: "town/community" — "Host Community"
    'events.status_happening' => "Noongom izhiwebad", // "Happening now" — noongom: "now" + izhiwebad: "it is happening"
    'events.status_upcoming' => "Bi-dagoshinomagad", // "Coming/Upcoming" — bi-: "coming" + dagoshinomagad: "it arrives"
    'events.status_past' => "Gaa-ishkwaa-ayaamagak", // "Past/Finished" — gaa-: past tense + ishkwaa: "finished" + ayaamagad: "it exists"
    'events.meta_date' => '',
    'events.meta_when' => '',
    'events.meta_location' => 'Endaad', // dict: endaad: "where located/home"
    'events.meta_type' => '',
    'events.meta_host' => '',
    'events.no_description' => '',
    'events.source_title' => '',
    'events.view_source' => '',
    'events.source_name' => '',
    'events.source_verified' => '',
    'events.source_card_label' => '',

    // Groups (businesses)
    'groups.title' => 'Anokiiwinan', // dict: anokiiwin: "work, activity, business" (plural)
    'groups.subtitle' => "Anokiiwinan, adaawewinan, miinawaa oodena-wiigwaamingan", // "Businesses, shops, and community buildings"
    'groups.empty_heading' => 'Anokiiwinan gaawiin mikanziinaawaa', // "No businesses found"
    'groups.empty_body' => "Anokiiwinan bi-dagoshinomagadoon waabang.", // "Businesses are coming soon."
    'groups.explore_button' => 'Naanaagadawaabandan oodenawinan', // "Explore communities"
    'groups.detail_back' => 'Anokiiwinan', // "Businesses"
    'groups.not_found' => 'Anokiiwin gaawiin mikanziin', // "Business not found"
    'groups.not_found_message' => "Anokiiwin gaa-nandawaabandaman gaawiin ayaasinoon.", // "The business you're looking for doesn't exist."
    'groups.browse_all' => 'Kakina anokiiwinan', // dict: kakina: "all"
    'groups.related_people' => 'Anishinaabeg', // dict: anishinaabeg: people (plural)
    'groups.related_events' => "Maawanji'idiwinan", // dict: maawanji'idiwag: "they come together, meet"
    'groups.related_teachings' => "Gikinoo'amaadiwinan", // dict: gikinoo'amaadiwin: "teaching, education"

    // Businesses (Adaawewinan)
    'businesses.title' => 'Adaawewinan', // dict: adaawewin: "business, store, trading place"
    'businesses.subtitle' => "Wiidookaw Anishinaabe adaawewinan gidoodenaang.", // "Support Indigenous businesses in your community."
    'businesses.badge' => 'Adaawewin', // "Business"
    'businesses.book_now' => 'Ozhitoon', // "Book/Make (an appointment)"
    'businesses.detail_back' => 'Kakina Adaawewinan', // "All Businesses"
    'businesses.visit_website' => 'Izhaan mazinaakowebiniganing', // "Go to website"
    'businesses.owner_heading' => 'Debendang', // "Owner" (one who owns it)
    'businesses.social_heading' => 'Oshki-dibaajimowinan', // "Latest news/updates"
    'businesses.not_found' => 'Adaawewin gaawiin mikanziin', // "Business not found"
    'businesses.not_found_message' => "Adaawewin gaa-nandawaabandaman gaawiin ayaasinoon.", // "The business you're looking for doesn't exist."
    'businesses.browse_all' => 'Kakina adaawewinan', // "All businesses"
    'businesses.empty_heading' => 'Adaawewinan gaawiin mikanziinaawaa', // "No businesses found"
    'businesses.empty_body' => "Anishinaabe adaawewinan bi-dagoshinomagadoon waabang.", // "Indigenous businesses are coming soon."
    'businesses.explore_button' => 'Naanaagadawaabandan oodenawinan', // "Explore communities"
    'businesses.location' => 'Endaad', // dict: endaad: "h/ home; h/ house" (location)
    'businesses.services' => 'Anokiiwinan miinawaa Miigiweng', // "Services and offerings"
    'businesses.community_affiliation' => 'Oodena', // dict: oodena: "town, community"

    // Featured (Maamawi-gizhendaagwak)
    'featured.section_title' => 'Maamawi-gizhendaagwak Misi-minis-akiing', // "Important things across Turtle Island"

    // Teachings
    'teachings.title' => 'Gikinoo\'amaadiwinan', // dict: gikinoo'amaadiwin: "teaching, education"
    'teachings.eyebrow' => 'Gikinoo\'amaadiwinan gichi-ayaa\'aag ininiwan', // "Teachings from Elders"
    'teachings.subtitle' => "Dibaajimowinan, gikendaasowinan, miinawaa gikinoo'amaadiwinan", // "Stories, knowledge, and teachings"
    'teachings.empty_heading' => "Gikinoo'amaadiwinan gaawiin mikanziinaawaa", // "No teachings found"
    'teachings.empty_body' => "Gikinoo'amaadiwinan bi-dagoshinomagadoon waabang.", // "Teachings are coming soon."
    'teachings.explore_language' => 'Naanaagadawaabandan Anishinaabemowin', // "Explore the language"
    'teachings.detail_back' => "Gikinoo'amaadiwinan", // "Teachings"
    'teachings.not_found' => "Gikinoo'amaadiwin gaawiin mikanziin", // "Teaching not found"
    'teachings.not_found_message' => "Gikinoo'amaadiwin gaa-nandawaabandaman gaawiin ayaasinoon.", // "The teaching you're looking for doesn't exist."
    'teachings.browse_all' => "Kakina gikinoo'amaadiwinan", // "All teachings"
    'teachings.related_events' => "Maawanji'idiwinan", // dict: maawanji'idiwag: "gatherings/events"
    'teachings.knowledge_keepers' => "Gechi-inendaagozijig", // "Knowledge Keepers" (those who are greatly valued)
    'teachings.source_label' => "Onji:", // dict: onji: "from, source"

    // Language / Dictionary
    'language.title' => 'Anishinaabemowin', // dict: Anishinaabemowin: "Ojibwe language"
    'language.subtitle' => 'Ikidowinan, ikidowinensag, miinawaa bizindamowin', // "Words, phrases, and listening"
    'language.empty_heading' => 'Ikidowinan gaawiin mikanziinaawaa', // "No words found"
    'language.empty_body' => 'Anishinaabemowin ikidowinan bi-dagoshinomagadoon waabang.', // "Language words are coming soon."
    'language.explore_teachings' => "Naanaagadawaabandan gikinoo'amaadiwinan", // "Explore teachings"
    'language.copyright' => "Ojibwe People's Dictionary onji mazina'iganan, miinawaa <a href=\"https://creativecommons.org/licenses/by-nc-sa/4.0/\" rel=\"external noopener\">CC BY-NC-SA 4.0</a> inaakoniganing aabajichigaadewan.", // dict: onji: "from/source"; mazina'igan: "document/data"; inaakonigan: "agreement/license"
    'language.detail_back' => 'Anishinaabemowin',
    'language.not_found' => 'Ikidowin gaawiin mikanziin', // "Word not found"
    'language.not_found_message' => 'Ikidowin gaa-nandawaabandaman gaawiin ayaasinoon.', // "The word you're looking for doesn't exist."
    'language.browse_all' => 'Kakina ikidowinan', // "All words"
    'language.entries_label' => 'ikidowinan', // "words"
    'language.prev' => 'Ishkweyaang', // "Back/Previous"
    'language.next' => 'Niigaan', // "Forward/Next"
    'language.page' => 'Ozhibii\'iganaak', // "Page"
    'language.search_placeholder' => 'Andone\' ikidowinan...', // "Search words..."
    'language.search_button' => 'Andone\'', // "Search"
    'language.search_results_title' => 'Ikidowinan Andone\'igen', // "Word Search"
    'language.search_results_count' => 'mikanziinaawaa', // "found"
    'language.search_no_results' => 'Gaawiin gegoo mikanziin. Gagwe-andone\' bakaan ikidowin.', // "Nothing found. Try a different word."
    'language.search_try_again' => 'Gagwe-andone\' miinawaa', // "Try searching again"

    // Search
    'search.title' => 'Andone\'igen', // dict: andone': "search for, seek"
    'search.placeholder' => 'Andone\'…', // dict: andone': "search for, seek"
    'search.button' => 'Andone\'', // dict: andone': "search for, seek"
    'search.filters_label' => 'Gashkitoonaanan:',
    'search.type_heading' => 'Izhichigewinan', // dict: izhichigewin: "an activity; a thing done"
    'search.sources_heading' => 'Onji', // dict: onji: "from, source"
    'search.scope' => 'Kakina oodenawinan miinawaa anokiiwinan.', // "Across all communities and organizations"
    'search.results_summary' => '{count} ezhichigewinan ({time}s)',
    'search.no_results' => 'Ezhichigewinan gaa-izhi-waabandamaan "{query}".',
    'search.did_you_mean' => 'Gidinendam ina', // "Did you mean"
    'search.pagination_prev' => '←',
    'search.pagination_next' => '→',
    'search.search_intro' => "Biindigen andone'iganing ji-mikamowad dibaajimowinan, gikinoo'amaadiwinan, maawanji'idiwinan, miinawaa oodena-mazina'iganan.", // "Use search to find stories, teachings, events, and community resources."
    'search.badge_event' => "Maawanji'idiwin", // dict: maawanji'idiwag: "gathering/event"
    'search.badge_teaching' => "Gikinoo'amaadiwin", // dict: gikinoo'amaadiwin: "teaching"
    'search.badge_group' => 'Wiijiiwaagan', // dict: wiijiiwaagan: "companion, group"
    'search.badge_person' => 'Anishinaabe', // dict: anishinaabe: "person"
    'search.badge_business' => 'Anokiiwin', // dict: anokiiwin: "work, activity"
    'search.badge_community' => 'Oodena', // dict: oodena: "town"
    'search.badge_page' => "Mazina'igan", // dict: mazina'igan: "page, document"

    // People
    'people.title' => 'Anishinaabeg', // dict: anishinaabeg: people (plural)
    'people.subtitle' => "Gichi-aya'aag, gikendaasowininiwag, miinawaa oodena-niigaanziijig", // "Elders, knowledge keepers, and community leaders"
    'people.browse_all' => 'Kakina anishinaabeg', // "All people"
    'people.nearby_notice' => 'Besho {community} ezhi-waabandaman.', // "Showing results near {community}."
    'people.show_all' => 'Kakina',
    'people.search_placeholder' => "Andone' anishinaabeg\u{2026}",
    'people.filter_role' => 'Ogimaawi',
    'people.filter_offering' => 'Miigiweng', // from existing "services & offerings" usage
    'people.filter_all_roles' => 'Kakina ogimaawiwinan', // "All roles"
    'people.filter_all_offerings' => 'Kakina miigiweng', // "All offerings"
    'people.clear_filters' => 'Aanjitoon', // dict: aanjitoon: "change/reset it"
    'people.mentor_callout' => 'Nandawenim na gagwe gikinoo\'amaagewin? Aabiding gichi-aya\'aag miinawaa gikendaasowininiwag gagwe wiidookawag oshki-bimaadizijig. Nandawaabam gichi-aya\'aag gemaa gikendaasowininiwag ji-mikamowad awiya besho gidakiing.', // Draft: "Looking for a mentor? ..."
    'people.empty_heading' => 'Anishinaabeg gaawiin mikanziinaawaa', // "No people found"
    'people.empty_body' => 'Gichi-aya\'aag, gikendaasowininiwag, inwewin-ikidowininiwag, oshki-ozhitoojig, miinawaa oodena-niigaanziijig omaa bi-waabanda\'og.', // Draft: "Elders, Knowledge Keepers, language speakers, makers, and leaders will appear here."
    'people.volunteer_button' => 'Wiidookaage',
    'people.detail_back' => 'Anishinaabeg',
    'people.not_found' => 'Anishinaabe gaawiin mikanziin', // "Person not found"
    'people.not_found_message' => 'Anishinaabe gaa-nandawaabandaman gaawiin ayaasinoon.', // "The person you're looking for doesn't exist."
    'people.filters_empty' => 'Gaawiin anishinaabeg mikanziinaawaa.', // "No people match your search or filters."
    'people.offerings_title' => 'Anokiiwinan miinawaa Miigiweng', // Services & Offerings
    'people.linked_business' => 'Adaawewin', // Business
    'people.community' => 'Oodena', // Community
    'people.related_events' => "Maawanji'idiwinan", // Related Events

    // Communities listing
    'communities.exploring' => 'Naanaagadawaabandamang', // "Exploring" (progressive form)
    'communities.all_communities' => 'Kakina oodenawinan', // dict: kakina: "all" + oodenawinan: "towns"
    'communities.search_placeholder' => 'Andawaabandan oodenawinan...', // dict: andawaabandan: "look for, search for"
    'communities.search_label' => 'Andawaabandan oodenawinan', // dict: andawaabandan: "look for, search for"
    'communities.all_types' => 'Kakina',
    'communities.first_nations' => 'Anishinaabeg', // dict: anishinaabeg: people (plural)
    'communities.municipalities' => 'Oodenawinan', // dict: oodena: "town" (plural)
    'communities.province' => 'Aki', // dict: aki: "land, earth, country"
    'communities.nation' => 'Ogimaawi-anishinaabeg',
    'communities.population' => 'Akiing-ogimaawag',
    'communities.under_500' => '< 500',
    'communities.pop_500_2000' => '500 – 2,000',
    'communities.pop_2000_5000' => '2,000 – 5,000',
    'communities.pop_5000_plus' => '5,000+',
    'communities.no_matches' => 'Oodenawinan gaawiin mikanziinaawaa.', // "No communities match your filters."

    // Community detail
    'community.back_button' => "\u{2190} Oodenawinan",
    'community.municipality' => 'Oodena', // dict: oodena: "town"
    'community.first_nation' => 'Anishinaabe', // dict: anishinaabe: "person, human being"
    'community.about' => 'Dibaajimowin', // dict: dibaajimowin: "a story; a narrative"
    'community.nation' => 'Ogimaawi-anishinaabeg',
    'community.language_group' => 'Inwewin', // dict: inwewin: "a language, a dialect"
    'community.treaty' => '',
    'community.reserve' => '',
    'community.inac_band_no' => 'INAC Band No.',
    'community.website' => "Web-mazina'igan",
    'community.leadership' => 'Ogimaawin', // dict: ogimaa: "chief, leader"
    'community.chief' => 'Ogimaa', // dict: ogimaa: "a chief, a boss"
    'community.current' => 'Noongom', // "Current / now"
    'community.councillor' => 'Wiiji-ogimaa', // Draft: "council/helper leader"
    'community.band_office' => 'Ogimaawiwigamig', // Draft: leadership/administration office
    'community.address' => 'Endaad', // dict: endaad: "h/ home; h/ house"
    'community.hours' => 'Dibik-giizhigad', // Draft label for hours/time of day
    'community.phone' => 'Giigidoo-makakoons', // dict: giigido: "speaks" + makakoons: "small box"
    'community.email' => 'Email',
    'community.toll_free' => 'Gaawiin gagwe-ataage', // Draft: "no charge"
    'community.fax' => 'Fax',
    'community.the_land' => 'Aki', // dict: aki: "land, earth, country"
    'community.openstreetmap' => 'OpenStreetMap',
    'community.google_maps' => 'Google Maps',
    'community.nearby_communities' => 'Besho oodenawinan', // "Nearby communities"
    'community.km_away' => 'km besho', // "km away/nearby"
    'community.pop' => 'Akiing-ogimaa', // Short population label matching communities.population root
    'community.local_events' => "Maawanji'idiwinan", // dict: maawanji'idiwag: "they come together, meet"
    'community.local_teachings' => "Gikinoo'amaadiwinan", // dict: gikinoo'amaadiwin: "teaching, education"
    'community.local_businesses' => 'Adaawewinan', // dict: adaawewin: "business, store, trading place"
    'community.local_people' => 'Anishinaabeg', // dict: anishinaabeg: people (plural)

    // Sagamok — Spanish River flood status (EN until community translation review)
    'sagamok_flood.title' => 'Spanish River flood response — {community}',
    'sagamok_flood.meta_description' => 'Community flood status for Sagamok Anishnawbek: river levels, boil water advisory, road access, contacts, and how to stay prepared.',
    'sagamok_flood.og_subtitle' => 'State of emergency',
    'sagamok_flood.og_image_cta' => 'Tap for river levels, contacts & how to stay safe →',
    'sagamok_flood.breadcrumb' => 'Spanish River flood response',
    'sagamok_flood.translation_pending' => 'Anishinaabemowin for this page is still being reviewed with a community speaker. Important safety details are shown in English for now.',
    'sagamok_flood.community_callout_title' => 'Spanish River flood response',
    'sagamok_flood.community_callout_body' => 'We are sharing river levels, water safety, road access, and contacts from Sagamok’s public notices so you have one place to check. Always follow Sagamok’s official posts if anything differs.',
    'sagamok_flood.community_callout_cta' => 'Open flood status page',
    'sagamok_flood.soe_eyebrow' => 'State of emergency — active',
    'sagamok_flood.soe_title' => 'Spanish River flood response',
    'sagamok_flood.soe_meta' => 'MNR flood warning in effect through Monday, April 27 · Last verified {date}',
    'sagamok_flood.official_label' => 'Official source',
    'sagamok_flood.official_text_before' => 'For official updates, visit the',
    'sagamok_flood.official_link' => 'Sagamok news feed',
    'sagamok_flood.glance_h' => 'At a glance',
    'sagamok_flood.tile_river_label' => 'River level',
    'sagamok_flood.tile_river_pill' => 'Rising',
    'sagamok_flood.tile_river_note' => 'Twice-daily measurements · Fire Department',
    'sagamok_flood.tile_hwy_label' => 'Hwy 7300 access',
    'sagamok_flood.tile_hwy_pill' => 'Monitoring',
    'sagamok_flood.tile_hwy_note' => 'Possible breach projected around April 20; Tiger Dam installed at Indian Head',
    'sagamok_flood.tile_bwa_label' => 'Drinking water',
    'sagamok_flood.tile_bwa_pill' => 'Boil advisory',
    'sagamok_flood.tile_bwa_note' => 'In effect since April 19 — until further notice',
    'sagamok_flood.tile_roads_label' => 'Interior roads',
    'sagamok_flood.tile_roads_pill' => 'Closures',
    'sagamok_flood.tile_roads_note' => 'River Road closed between Abitong and Owl residences',
    'sagamok_flood.contacts_h' => 'Critical contacts',
    'sagamok_flood.contacts_verified' => 'Numbers as listed on Sagamok’s flood-warning notice. Last verified {date}.',
    'sagamok_flood.contacts_notice_link' => 'Open Sagamok’s flood-warning notice',
    'sagamok_flood.c_911_name' => 'Emergency',
    'sagamok_flood.c_911_role' => 'Fire · Police · Ambulance',
    'sagamok_flood.c_crisis_name' => 'Crisis counselling',
    'sagamok_flood.c_crisis_role' => '24/7 mental health support',
    'sagamok_flood.c_victim_name' => 'Manitoulin Northshore Victim Services',
    'sagamok_flood.c_victim_role' => 'Support after crime or tragedy',
    'sagamok_flood.c_admin_name' => 'Sagamok administration',
    'sagamok_flood.c_admin_role' => 'Main switchboard — medical, housing, environmental, justice, FCSS',
    'sagamok_flood.contacts_note' => 'For urgent departmental matters during the flood response, call the administration line and ask to be routed. Direct lines for staff are on Sagamok’s flood-warning notice.',
    'sagamok_flood.contacts_notice_link_short' => 'flood-warning notice',
    'sagamok_flood.bwa_h' => 'Boil water advisory',
    'sagamok_flood.bwa_tag' => 'Active — in effect since April 19',
    'sagamok_flood.bwa_how_h' => 'How to make water safe to drink',
    'sagamok_flood.bwa_how_p' => 'Bring tap water to a rolling boil for at least one minute, then let it cool in a clean covered container. Use boiled or bottled water for drinking, preparing food, making ice, brushing teeth, and washing fruit or vegetables.',
    'sagamok_flood.bwa_when_h' => 'When will the advisory lift?',
    'sagamok_flood.bwa_when_p' => 'The advisory stays in place until further notice. We will refresh this page when Sagamok posts an update.',
    'sagamok_flood.roads_h' => 'Roads and access',
    'sagamok_flood.roads_tag' => 'Active monitoring',
    'sagamok_flood.roads_7300_h' => 'Highway 7300 — main access road',
    'sagamok_flood.roads_7300_p' => 'A possible breach was projected on or around April 20. Sagamok Fire is measuring river levels twice daily. A Tiger Dam is in place at Indian Head to protect the highway. If conditions turn critical, you should hear from Everbridge and Sagamok’s social channels.',
    'sagamok_flood.roads_river_h' => 'River Road',
    'sagamok_flood.roads_river_p' => 'Closed between Marlene and Silas Abitong’s residence and Raymond and Janet Owl’s residence. Many interior roads are flooded — take it slow.',
    'sagamok_flood.prep_h' => 'Be prepared',
    'sagamok_flood.prep_1' => 'Put together a kit: food, water, medications, and important documents.',
    'sagamok_flood.prep_2' => 'Fuel vehicles and portable containers.',
    'sagamok_flood.prep_3' => 'Register for emergency alerts through Everbridge.',
    'sagamok_flood.prep_4' => 'Check on Elders and neighbours who need extra support.',
    'sagamok_flood.prep_5' => 'Plan for possible evacuation, especially if mobility is limited.',
    'sagamok_flood.prep_6' => 'Keep children and pets away from flooded areas.',
    'sagamok_flood.prep_7' => 'Slow down on the roads — do not drive through standing water.',
    'sagamok_flood.timeline_h' => 'Timeline of updates',
    'sagamok_flood.t_22_date' => 'April 22',
    'sagamok_flood.t_22_body' => 'We re-verified contacts and notices against Sagamok’s public posts and refreshed the on-the-ground photo set on this page.',
    'sagamok_flood.t_21_date' => 'April 21 — morning',
    'sagamok_flood.t_21_body' => 'We published this community status page on Minoo as a companion to Sagamok’s official communications. We will update it when new information is released.',
    'sagamok_flood.t_19_date' => 'April 19',
    'sagamok_flood.t_19_body' => 'Precautionary boil water advisory issued — until further notice. Tiger Dam installation begins on Highway 7300 at Indian Head.',
    'sagamok_flood.t_18_date' => 'April 18',
    'sagamok_flood.t_18_body' => 'Sagamok declares a state of emergency. MNR Sudbury District extends the Spanish River flood warning through April 27.',
    'sagamok_flood.t_14_date' => 'April 14',
    'sagamok_flood.t_14_body' => 'Flood warning in effect for Sagamok Anishnawbek territory.',
    'sagamok_flood.disclaimer_1' => 'We summarize public notices from Sagamok so you can find key information quickly. This is not an official Sagamok channel. If anything here disagrees with a Sagamok notice, follow the official notice.',
    'sagamok_flood.disclaimer_2' => 'We host this page in Canada on infrastructure we operate with Indigenous partners.',
    'sagamok_flood.footer_updated' => 'Last updated {date}.',
    'sagamok_flood.back_top' => 'Back to top',
    'sagamok_flood.gallery_h' => 'Photos from the territory',
    'sagamok_flood.gallery_alt_1' => 'Flooding and high water near Sagamok Anishnawbek — photo refreshed April 22, 2026.',
    'sagamok_flood.gallery_alt_2' => 'Spanish River corridor flood conditions — photo refreshed April 22, 2026.',
    'sagamok_flood.gallery_alt_3' => 'Community flood response along roads and shoreline — photo refreshed April 22, 2026.',
    'sagamok_flood.gallery_alt_4' => 'Flood conditions on territory — use caution near moving water; photo refreshed April 22, 2026.',
    'sagamok_flood.gallery_cap_1' => 'Spanish River flood — road and water levels (photos updated April 22, 2026).',
    'sagamok_flood.gallery_cap_2' => 'Spanish River flood — shoreline and access routes (photos updated April 22, 2026).',
    'sagamok_flood.gallery_cap_3' => 'Spanish River flood — interior roads and low-lying ground (photos updated April 22, 2026).',
    'sagamok_flood.gallery_cap_4' => 'Spanish River flood — conditions on Sagamok Anishnawbek territory (photos updated April 22, 2026).',

    // Crisis pages — shared chrome (EN until translated)
    'crisis.common.official_label' => 'Official source',
    'crisis.common.glance_h' => 'At a glance',
    'crisis.common.contacts_h' => 'Critical contacts',
    'crisis.common.prep_h' => 'Be prepared',
    'crisis.common.prep_1' => 'Put together a kit: food, water, medications, and important documents.',
    'crisis.common.prep_2' => 'Fuel vehicles and portable containers.',
    'crisis.common.prep_3' => 'Check on Elders and neighbours who need extra support.',

    // Sudbury SOE — EN placeholders (same as en.php until community translation review)
    'sudbury_soe.translation_pending' => 'Anishinaabemowin for this page is still being reviewed. Important safety details are shown in English for now.',
    'sudbury_soe.title' => 'Municipal emergency status — {community}',
    'sudbury_soe.og_subtitle' => 'Municipal emergency',
    'sudbury_soe.og_image_cta' => '',
    'sudbury_soe.meta_description' => 'Placeholder emergency status page for Greater Sudbury. Verify against official city communications before relying on this page.',
    'sudbury_soe.breadcrumb' => 'Municipal emergency status',
    'sudbury_soe.gallery_h' => 'Photos',
    'sudbury_soe.soe_eyebrow' => 'Draft — not published',
    'sudbury_soe.soe_title' => 'Greater Sudbury emergency status',
    'sudbury_soe.soe_meta' => 'Draft page · Last verified {date}',
    'sudbury_soe.official_text_before' => 'For official updates, visit',
    'sudbury_soe.official_link' => 'City of Greater Sudbury',
    'sudbury_soe.timeline_h' => 'Timeline of updates',
    'sudbury_soe.t_placeholder_date' => 'April 22',
    'sudbury_soe.t_placeholder_body' => 'Replace this entry with dated facts from the city’s official channels once verified.',
    'sudbury_soe.tile_status_label' => 'Municipal status',
    'sudbury_soe.tile_status_pill' => 'Draft',
    'sudbury_soe.tile_status_note' => 'This incident is marked draft in Minoo until official links and wording are verified.',
    'sudbury_soe.contacts_verified' => 'Contacts will be listed from official city sources. Last verified {date}.',
    'sudbury_soe.contacts_notice_link' => 'Open City of Greater Sudbury',
    'sudbury_soe.contacts_note' => 'Follow the municipality’s official posts for evacuation, shelters, and service changes.',
    'sudbury_soe.contacts_notice_link_short' => 'city website',
    'sudbury_soe.c_911_name' => 'Emergency',
    'sudbury_soe.c_911_role' => 'Police · Fire · Ambulance',
    'sudbury_soe.info_h' => 'What we will add next',
    'sudbury_soe.info_tag' => 'Draft',
    'sudbury_soe.info_p1_h' => 'Official sources',
    'sudbury_soe.info_p1_body' => 'After verification, this section will summarize the declared emergency, affected services, and where to get help — with links to the city’s official notices.',
    'sudbury_soe.disclaimer_1' => 'This draft page is not an official City of Greater Sudbury channel. Do not rely on it until Minoo marks the incident as published.',
    'sudbury_soe.disclaimer_2' => 'We host crisis companion pages in Canada on infrastructure we operate with Indigenous partners.',
    'sudbury_soe.footer_updated' => 'Last updated {date}.',
    'sudbury_soe.back_top' => 'Back to top',
    'sudbury_soe.community_callout_title' => 'Greater Sudbury emergency status',
    'sudbury_soe.community_callout_body' => 'When published, this page will summarize official municipal notices in one place.',
    'sudbury_soe.community_callout_cta' => 'Open emergency status page',

    // About
    'about.title' => 'Dibaajimowin', // dict: dibaajimowin: "a story; a narrative; a report"
    'about.subtitle' => '',
    'about.name_heading' => '',
    'about.name_definition' => '',
    'about.what_heading' => '',
    'about.what_intro' => '',
    'about.what_intro2' => '',
    'about.what_list_1' => '',
    'about.what_list_2' => '',
    'about.what_list_3' => '',
    'about.what_list_4' => '',
    'about.for_heading' => '',
    'about.for_intro' => '',
    'about.for_list_1' => '',
    'about.for_list_2' => '',
    'about.for_list_3' => '',
    'about.for_list_4' => '',
    'about.for_list_5' => '',
    'about.how_heading' => '',
    'about.how_desc1' => '',
    'about.how_desc2' => '',
    'about.community_heading' => '',
    'about.community_desc1' => '',
    'about.community_desc2' => '',
    'about.vision_heading' => '',
    'about.vision_desc' => '',

    // Error pages
    'error.404_title' => "Mazina'igan gaawiin mikanziin", // "Page not found"
    'error.404_message' => "Mazina'igan gaa-nandawaabandaman gaawiin ayaasinoon.", // "The page you're looking for doesn't exist."
    'error.404_home' => 'Endaayan', // dict: endaayan: "your home" — go home
    'error.403_signin' => 'Biindigen',
    'error.403_home' => 'Endaad',

    // Authentication
    'auth.login_title' => 'Biindigen', // dict: biindige: "enter, go inside"
    'auth.login_subtitle' => '',
    'auth.email' => '',
    'auth.password' => '',
    'auth.login_button' => 'Biindigen', // dict: biindige: "enter, go inside"
    'auth.forgot_password' => '',
    'auth.no_account' => '',
    'auth.create_account_link' => '',
    'auth.register_title' => '',
    'auth.register_subtitle' => '',
    'auth.name' => 'Izhinikaazowin', // dict: izhinikaazo: "s/he is named a certain way"
    'auth.phone' => 'Giigidoo-makakoons', // dict: giigido: "speaks" + makakoons: "small box"
    'auth.optional' => '',
    'auth.register_button' => '',
    'auth.have_account' => '',
    'auth.login_link' => 'Biindigen', // dict: biindige: "enter, go inside"
    'auth.forgot_title' => '',
    'auth.forgot_intro' => '',
    'auth.reset_link_sent' => '',
    'auth.reset_button' => '',
    'auth.back_to_login' => '',
    'auth.reset_title' => '',
    'auth.reset_error_link' => '',
    'auth.new_password' => '',
    'auth.confirm_password' => '',
    'auth.reset_submit' => '',
    'auth.check_email_title' => '',
    'auth.check_email_message' => '',
    'auth.check_email_note' => '',
    'auth.verify_title' => '',
    'auth.verify_success' => '',
    'auth.verify_error_title' => '',
    'auth.verify_error_invalid' => '',
    'auth.verify_error_user' => '',

    // Account
    'account.welcome' => 'Boozhoo', // dict: boozhoo': "say hello"
    'account.subtitle' => '',
    'account.profile' => '',
    'account.logout' => 'Zaaga\'an', // dict: zaaga'am: "s/he goes out, exits"
    'account.elder_support' => 'Gichi-aya\'aa Wiidookaagewin', // dict: gichi-aya'aa: "elder" + wiidookaagewin: "help"
    'account.volunteer_text' => '',
    'account.my_assignments' => '',
    'account.coordination' => '',
    'account.coordinator_text' => '',
    'account.coordinator_dashboard' => '',

    // Elder Support page
    'elders.title' => 'Gichi-aya\'aa Wiidookaagewin', // dict: gichi-aya'aa: "elder" + wiidookaagewin: "help"
    'elders.subtitle' => '',
    'elders.how_heading' => '',
    'elders.step1_title' => '',
    'elders.step1_text' => '',
    'elders.step2_title' => '',
    'elders.step2_text' => '',
    'elders.step3_title' => '',
    'elders.step3_text' => '',
    'elders.for_elders_heading' => '',
    'elders.for_elders_text' => '',
    'elders.for_elders_step1' => '',
    'elders.for_elders_step2' => '',
    'elders.for_elders_step3' => '',
    'elders.for_elders_step4' => '',
    'elders.request_help_button' => '',
    'elders.for_volunteers_heading' => '',
    'elders.for_volunteers_text' => '',
    'elders.for_volunteers_step1' => '',
    'elders.for_volunteers_step2' => '',
    'elders.for_volunteers_step3' => '',
    'elders.for_volunteers_step4' => '',
    'elders.volunteer_button' => 'Wiidookaage', // dict: wiidookaage: "s/he helps people"
    'elders.prefer_call_heading' => '',
    'elders.prefer_call_text' => '',
    'elders.safety_heading' => '',
    'elders.safety_text' => '',
    'elders.safety_link' => '',

    // Elder Support request form
    'request.title' => 'Gichi-aya\'aa Wiidookaagewin', // dict: gichi-aya'aa: "elder" + wiidookaagewin: "help"
    'request.subtitle' => '',
    'request.your_name' => 'Gidizhinikaazowin', // dict: gid- (your) + izhinikaazowin (name)
    'request.on_behalf' => '',
    'request.elder_name' => 'Gichi-aya\'aa Izhinikaazowin', // dict: gichi-aya'aa: elder + izhinikaazowin: name
    'request.consent' => '',
    'request.phone' => 'Giigidoo-makakoons', // dict: giigido: "speaks" + makakoons: "small box"
    'request.community' => 'Oodena', // dict: oodena: "town"
    'request.type_of_help' => '',
    'request.select_default' => '',
    'request.type_ride' => '',
    'request.type_groceries' => '',
    'request.type_chores' => '',
    'request.type_visit' => '',
    'request.optional' => '',
    'request.additional_notes' => '',
    'request.privacy_note' => '',
    'request.privacy_link' => '',
    'request.submit_button' => 'Izhinizha\'an', // dict: izhinizha'an: send it to a certain place
    'request.can_help' => '',
    'request.volunteer_link' => '',
    'request.safety_matters' => '',
    'request.safety_link' => '',

    // Request confirmation
    'request_confirm.badge' => '',
    'request_confirm.title' => '',
    'request_confirm.reference' => '',
    'request_confirm.message' => '',
    'request_confirm.cancel_note' => '',
    'request_confirm.type_of_help' => '',
    'request_confirm.community' => 'Oodena', // dict: oodena: "town"
    'request_confirm.requested_for' => '',
    'request_confirm.notes' => '',
    'request_confirm.what_next' => '',
    'request_confirm.next_step1' => '',
    'request_confirm.next_step2' => '',
    'request_confirm.next_step3' => '',
    'request_confirm.next_step4' => '',
    'request_confirm.submit_another' => '',
    'request_confirm.not_found_title' => '',
    'request_confirm.not_found_text' => '',
    'request_confirm.submit_new' => '',

    // Volunteer signup
    'volunteer_signup.title' => 'Wiidookaage', // dict: wiidookaage: "s/he helps people"
    'volunteer_signup.subtitle' => '',
    'volunteer_signup.intro_text' => '',
    'volunteer_signup.your_name' => 'Gidizhinikaazowin', // dict: gid- (your) + izhinikaazowin (name)
    'volunteer_signup.phone' => 'Giigidoo-makakoons', // dict: giigido: "speaks" + makakoons: "small box"
    'volunteer_signup.community' => 'Oodena', // dict: oodena: "town"
    'volunteer_signup.availability' => '',
    'volunteer_signup.availability_placeholder' => '',
    'volunteer_signup.max_travel' => '',
    'volunteer_signup.max_travel_placeholder' => '',
    'volunteer_signup.optional' => '',
    'volunteer_signup.skills' => '',
    'volunteer_signup.skills_legend' => '',
    'volunteer_signup.additional_notes' => '',
    'volunteer_signup.privacy_note' => '',
    'volunteer_signup.privacy_link' => '',
    'volunteer_signup.submit_button' => 'Izhinizha\'an', // dict: izhinizha'an: send it
    'volunteer_signup.need_help' => '',
    'volunteer_signup.request_link' => '',

    // Volunteer confirmation
    'volunteer_confirm.badge' => '',
    'volunteer_confirm.title' => 'Miigwech', // dict: miigwech: "thanks!"
    'volunteer_confirm.message' => '',
    'volunteer_confirm.availability' => '',
    'volunteer_confirm.skills' => '',
    'volunteer_confirm.notes' => '',
    'volunteer_confirm.what_next' => '',
    'volunteer_confirm.next_step1' => '',
    'volunteer_confirm.next_step2' => '',
    'volunteer_confirm.next_step3' => '',
    'volunteer_confirm.signup_another' => '',
    'volunteer_confirm.not_found_title' => '',
    'volunteer_confirm.not_found_text' => '',
    'volunteer_confirm.signup_link' => '',

    // Coordinator dashboard
    'coordinator.title' => '',
    'coordinator.subtitle' => '',
    'coordinator.open_requests' => '',
    'coordinator.no_open_requests' => '',
    'coordinator.type' => '',
    'coordinator.phone' => 'Giigidoo-makakoons', // dict: giigido: "speaks" + makakoons: "small box"
    'coordinator.community' => 'Oodena', // dict: oodena: "town"
    'coordinator.notes' => '',
    'coordinator.assign_to' => '',
    'coordinator.select_volunteer' => '',
    'coordinator.assign_button' => '',
    'coordinator.cancel_request' => '',
    'coordinator.reason' => '',
    'coordinator.confirm_cancel' => '',
    'coordinator.assigned_in_progress' => '',
    'coordinator.no_assigned' => '',
    'coordinator.volunteer' => 'Wiidookaage', // dict: wiidookaage: "s/he helps people"
    'coordinator.reassign' => '',
    'coordinator.reassign_to' => '',
    'coordinator.pending_confirmation' => '',
    'coordinator.no_pending' => '',
    'coordinator.completed' => '',
    'coordinator.confirm_completion' => '',
    'coordinator.volunteer_pool' => '',
    'coordinator.no_volunteers' => '',
    'coordinator.availability' => '',
    'coordinator.history' => '',
    'coordinator.no_history' => '',
    'coordinator.confirmed' => '',
    'coordinator.cancelled' => '',

    // Volunteer dashboard
    'volunteer_dash.title' => 'Nindanokiiwin', // dict: nind- (my) + anokiiwin (work)
    'volunteer_dash.subtitle' => '',
    'volunteer_dash.status' => '',
    'volunteer_dash.edit_profile' => 'Aanjitoon', // dict: aanjitoon: change it
    'volunteer_dash.go_active' => '',
    'volunteer_dash.go_unavailable' => '',
    'volunteer_dash.no_assignments' => '',
    'volunteer_dash.assigned_to_you' => '',
    'volunteer_dash.type' => '',
    'volunteer_dash.phone' => 'Giigidoo-makakoons', // dict: giigido: "speaks" + makakoons: "small box"
    'volunteer_dash.community' => 'Oodena', // dict: oodena: "town"
    'volunteer_dash.notes' => '',
    'volunteer_dash.accept_start' => '',
    'volunteer_dash.decline' => '',
    'volunteer_dash.assigned' => '',
    'volunteer_dash.in_progress' => '',
    'volunteer_dash.mark_complete' => '',
    'volunteer_dash.how_did_it_go' => '',
    'volunteer_dash.confirm_complete' => '',
    'volunteer_dash.completed_awaiting' => '',
    'volunteer_dash.completed' => '',
    'volunteer_dash.waiting_confirmation' => '',
    'volunteer_dash.confirmed' => '',
    'volunteer_dash.history' => '',

    // Volunteer landing page
    'volunteer_page.title' => '',
    'volunteer_page.subtitle' => '',
    'volunteer_page.signup_button' => 'Wiidookaage', // dict: wiidookaage: "s/he helps people"
    'volunteer_page.login_button' => 'Biindigen', // dict: biindige: "enter, go inside"
    'volunteer_page.why_heading' => '',
    'volunteer_page.reason1_title' => '',
    'volunteer_page.reason1_text' => '',
    'volunteer_page.reason2_title' => '',
    'volunteer_page.reason2_text' => '',
    'volunteer_page.reason3_title' => '',
    'volunteer_page.reason3_text' => '',
    'volunteer_page.how_heading' => '',
    'volunteer_page.how_step1' => '',
    'volunteer_page.how_step2' => '',
    'volunteer_page.how_step3' => '',
    'volunteer_page.how_step4' => '',
    'volunteer_page.learn_more' => '',
    'volunteer_page.safety_heading' => '',
    'volunteer_page.safety_text' => '',
    'volunteer_page.safety_link' => '',

    // Safety
    'safety.title' => 'Ganawendan', // dict: ganawendan: "take care of, protect, keep it"
    'safety.subtitle' => '',
    'safety.elders_heading' => '',
    'safety.elders_list_1' => '',
    'safety.elders_list_2' => '',
    'safety.elders_list_3' => '',
    'safety.elders_list_4' => '',
    'safety.elders_list_5' => '',
    'safety.volunteers_heading' => '',
    'safety.volunteers_list_1' => '',
    'safety.volunteers_list_2' => '',
    'safety.volunteers_list_3' => '',
    'safety.volunteers_list_4' => '',
    'safety.volunteers_list_5' => '',
    'safety.volunteers_list_6' => '',
    'safety.expect_heading' => '',
    'safety.expect_intro' => '',
    'safety.expect_list_1' => '',
    'safety.expect_list_2' => '',
    'safety.expect_list_3' => '',
    'safety.expect_list_4' => '',
    'safety.concerns_heading' => '',
    'safety.concerns_desc' => '',
    'safety.emergency' => '',
    'safety.emergency_note' => '',
    'safety.request_button' => '',
    'safety.volunteer_button' => '',

    // How it works
    'how.title' => '',
    'how.subtitle' => '',
    'how.elders_heading' => '',
    'how.elders_list_1' => '',
    'how.elders_list_2' => '',
    'how.elders_list_3' => '',
    'how.elders_list_4' => '',
    'how.request_button' => '',
    'how.volunteers_heading' => '',
    'how.volunteers_list_1' => '',
    'how.volunteers_list_2' => '',
    'how.volunteers_list_3' => '',
    'how.volunteers_list_4' => '',
    'how.volunteer_button' => 'Wiidookaage', // dict: wiidookaage: "s/he helps people"
    'how.behalf_heading' => '',
    'how.behalf_intro' => '',
    'how.behalf_list_1' => '',
    'how.behalf_list_2' => '',
    'how.behalf_list_3' => '',
    'how.behalf_note' => '',
    'how.faq_heading' => '',
    'how.faq_cost' => '',
    'how.faq_cost_answer' => '',
    'how.faq_screening' => '',
    'how.faq_screening_answer' => '',
    'how.faq_urgent' => '',
    'how.faq_urgent_answer' => '',
    'how.faq_cancel' => '',
    'how.faq_cancel_answer' => '',

    // Data Sovereignty
    'data.title' => '',
    'data.subtitle' => '',
    'data.what_heading' => '',
    'data.what_intro' => '',
    'data.what_list_1' => '',
    'data.what_list_2' => '',
    'data.what_list_3' => '',
    'data.what_list_4' => '',
    'data.what_list_5' => '',
    'data.what_list_6' => '',
    'data.never_heading' => '',
    'data.never_intro' => '',
    'data.never_list_1' => '',
    'data.never_list_2' => '',
    'data.never_list_3' => '',
    'data.never_list_4' => '',
    'data.never_list_5' => '',
    'data.consent_heading' => '',
    'data.consent_intro' => '',
    'data.consent_list_1' => '',
    'data.consent_list_2' => '',
    'data.consent_note' => '',
    'data.who_heading' => '',
    'data.who_desc1' => '',
    'data.who_desc2' => '',
    'data.who_desc3' => '',
    'data.learning_heading' => '',
    'data.learning_desc1' => '',
    'data.learning_desc2' => '',
    'data.learning_desc3' => '',
    'data.learning_desc4' => '',
    'data.questions_heading' => '',
    'data.questions_desc1' => '',
    'data.questions_desc2' => '',
    'data.questions_desc3' => '',

    // Legal pages
    'legal.main_title' => '',
    'legal.main_subtitle' => '',
    'legal.main_privacy_title' => '',
    'legal.main_privacy_desc' => '',
    'legal.main_terms_title' => '',
    'legal.main_terms_desc' => '',
    'legal.main_accessibility_title' => '',
    'legal.main_accessibility_desc' => '',
    'legal.privacy_title' => '',
    'legal.privacy_updated' => '',
    'legal.privacy_collect_heading' => '',
    'legal.privacy_collect_intro' => '',
    'legal.privacy_collect_contact' => '',
    'legal.privacy_collect_community' => '',
    'legal.privacy_collect_location' => '',
    'legal.privacy_collect_request' => '',
    'legal.privacy_collect_volunteer' => '',
    'legal.privacy_use_heading' => '',
    'legal.privacy_use_connect' => '',
    'legal.privacy_use_coordinators' => '',
    'legal.privacy_use_display' => '',
    'legal.privacy_location_heading' => '',
    'legal.privacy_location_intro' => '',
    'legal.privacy_location_nearest' => '',
    'legal.privacy_location_sort' => '',
    'legal.privacy_location_homepage' => '',
    'legal.privacy_location_optional' => '',
    'legal.privacy_storage_heading' => '',
    'legal.privacy_storage_desc' => '',
    'legal.privacy_rights_heading' => '',
    'legal.privacy_rights_desc' => '',
    'legal.terms_title' => '',
    'legal.terms_updated' => '',
    'legal.terms_about_heading' => '',
    'legal.terms_about_desc' => '',
    'legal.terms_responsibilities_heading' => '',
    'legal.terms_responsibilities_accurate' => '',
    'legal.terms_responsibilities_respect' => '',
    'legal.terms_responsibilities_safety' => '',
    'legal.terms_responsibilities_report' => '',
    'legal.terms_coordinator_heading' => '',
    'legal.terms_coordinator_desc' => '',
    'legal.terms_liability_heading' => '',
    'legal.terms_liability_desc' => '',
    'legal.terms_changes_heading' => '',
    'legal.terms_changes_desc' => '',
    'legal.accessibility_title' => '',
    'legal.accessibility_updated' => '',
    'legal.accessibility_commitment_heading' => '',
    'legal.accessibility_commitment_desc' => '',
    'legal.accessibility_features_heading' => '',
    'legal.accessibility_features_semantic' => '',
    'legal.accessibility_features_skip' => '',
    'legal.accessibility_features_aria' => '',
    'legal.accessibility_features_contrast' => '',
    'legal.accessibility_features_forms' => '',
    'legal.accessibility_features_responsive' => '',
    'legal.accessibility_phone_heading' => '',
    'legal.accessibility_phone_desc' => '',
    'legal.accessibility_feedback_heading' => '',
    'legal.accessibility_feedback_desc' => '',

    // Volunteer profile edit
    'volunteer_edit.title' => 'Aanjitoon', // dict: aanjitoon: change it
    'volunteer_edit.subtitle' => '',
    'volunteer_edit.phone' => 'Giigidoo-makakoons', // dict: giigido: "speaks" + makakoons: "small box"
    'volunteer_edit.availability' => '',
    'volunteer_edit.availability_placeholder' => '',
    'volunteer_edit.max_travel' => '',
    'volunteer_edit.max_travel_placeholder' => '',
    'volunteer_edit.skills' => '',
    'volunteer_edit.skills_legend' => '',
    'volunteer_edit.additional_notes' => '',
    'volunteer_edit.save_button' => 'Ganawenjigewin', // dict: ganawendan: "take care of, keep"
    'volunteer_edit.cancel' => 'Ishkwaa', // dict: ishkwaa: stop, cease

    // Breadcrumb
    'breadcrumb.home' => "Waakaa'iganing", // dict: waakaa'igan: "house, home" + locative -ing: "at home"

    // Open Graph / SEO
    'og.default_description' => '',

    // Games hub
    'games.title' => '', // needs translation
    'games.subtitle' => '', // needs translation
    'games.available_games' => '', // needs translation
    'games.word_game_badge' => '', // needs translation
    'games.shkoda_title' => 'Shkoda', // proper name — no translation needed
    'games.shkoda_description' => '', // needs translation
    'games.crossword_title' => '', // needs translation
    'games.crossword_description' => '', // needs translation
    'games.word_match_title' => '', // needs translation
    'games.word_match_description' => '', // needs translation
    'games.play_now' => '', // needs translation
    'games.more_coming' => '', // needs translation
    'games.listening_quiz' => '', // needs translation
    'games.sentence_builder' => '', // needs translation
    'games.breadcrumb' => '', // needs translation
    'games.game_mode' => '', // needs translation
    'games.daily' => '', // needs translation
    'games.practice' => '', // needs translation
    'games.streak' => '', // needs translation
    'games.themes' => '', // needs translation
    'games.easy' => '', // needs translation
    'games.medium' => '', // needs translation
    'games.hard' => '', // needs translation
    'games.easy_4' => '', // needs translation
    'games.medium_6' => '', // needs translation
    'games.hard_8' => '', // needs translation
    'games.difficulty' => '', // needs translation
    'games.direction' => '', // needs translation
    'games.ojibwe_to_english' => '', // needs translation
    'games.english_to_ojibwe' => '', // needs translation
    'games.ojibwe' => 'Anishinaabemowin', // dict: Anishinaabemowin: "Ojibwe language"
    'games.english' => 'Zhaaganaashiimowin', // dict: zhaaganaashiimowin: "English language"
    'games.matching_board' => '', // needs translation
    'games.time' => '', // needs translation
    'games.matched' => '', // needs translation
    'games.wrong' => '', // needs translation
    'games.all_matched' => '', // needs translation
    'games.attempts' => '', // needs translation
    'games.accuracy' => '', // needs translation
    'games.play_again' => '', // needs translation
    'games.share' => '', // needs translation
    'games.next_puzzle' => '', // needs translation
    'games.across' => '', // needs translation
    'games.down' => '', // needs translation
    'games.crossword_grid' => '', // needs translation
    'games.word_bank' => '', // needs translation
    'games.loading' => '', // needs translation
    'games.loading_puzzle' => '', // needs translation
    'games.swap_direction' => '', // needs translation
    'games.campfire' => 'Shkoda', // dict: shkoda: fire
    'games.guesses_remaining' => '', // needs translation
    'games.guesses_remaining_default' => '', // needs translation
    'games.guess_word_for' => '', // needs translation
    'games.wrong_guesses' => '', // needs translation
    'games.game_loaded' => '', // needs translation
    'games.correct_match' => '', // needs translation
    'games.incorrect_match' => '', // needs translation
    'games.game_complete' => '', // needs translation
    'games.failed_load' => '', // needs translation
    'games.error' => '', // needs translation
    'games.connection_error' => '', // needs translation
    'games.copied' => '', // needs translation
    'games.live_game_badge' => '', // needs translation
    'games.guess_price_title' => '', // needs translation — proper title may stay English in UI
    'games.guess_price_description' => '', // needs translation
    'games.guess_price_select_title' => '', // needs translation
    'games.guess_price_select_hint' => '', // needs translation
    'games.guess_price_continue' => '', // needs translation
    'games.guess_price_guess_heading' => '', // needs translation
    'games.guess_price_guess_for' => '', // needs translation
    'games.guess_price_lock_in' => '', // needs translation
    'games.guess_price_reveal_front' => '', // needs translation
    'games.guess_price_reveal_back' => '', // needs translation
    'games.guess_price_you_guessed' => '', // needs translation
    'games.guess_price_actual_price' => '', // needs translation
    'games.guess_price_win_title' => '', // needs translation
    'games.guess_price_lose_title' => '', // needs translation
    'games.guess_price_win_explain' => '', // needs translation
    'games.guess_price_lose_explain' => '', // needs translation
    'games.guess_price_next_item' => '', // needs translation
    'games.guess_price_finish_round' => '', // needs translation
    'games.guess_price_all_done_title' => '', // needs translation
    'games.guess_price_all_done_body' => '', // needs translation
    'games.guess_price_loading' => '', // needs translation
    'games.guess_price_error_load' => '', // needs translation
    'games.guess_price_retry' => '', // needs translation
    'games.guess_price_item_progress' => '', // needs translation
    'games.guess_price_currency' => 'CAD',
    'games.guess_price_slider_label' => '', // needs translation
    'games.guess_price_number_label' => '', // needs translation

    // Messages — additional
    'messages.chats_title' => '', // needs translation
    'messages.new_message' => '', // needs translation

    // Feed — additional
    'feed.edit_post' => '', // needs translation
    'feed.cancel' => '', // needs translation
    'feed.save' => 'Ganawenjigewin', // dict: ganawendan: "take care of, keep"
    'feed.edit' => '', // needs translation
    'feed.delete' => '', // needs translation
    'feed.post_options' => '', // needs translation
    'feed.source_minoo' => 'Minoo',
    'feed.all_caught_up' => '', // needs translation
    'feed.just_now' => '', // needs translation
    'feed.write_comment' => '', // needs translation
    'feed.delete_confirm' => '', // needs translation
    'feed.deleting' => '', // needs translation
    'feed.posting' => '', // needs translation
    'feed.network_error' => '', // needs translation
    'feed.error_try_again' => '', // needs translation
    'feed.could_not_load_comments' => '', // needs translation
    'feed.copied' => '', // needs translation
    'feed.saving' => '', // needs translation
    'feed.could_not_load_post' => '', // needs translation
    'feed.could_not_update_post' => '', // needs translation
    'feed.could_not_delete_post' => '', // needs translation
    'feed.you' => '', // needs translation
    'feed.post_comment' => '', // needs translation
    'feed.error' => '', // needs translation

    // Contributors
    'contributors.title' => '', // needs translation
    'contributors.subtitle' => '', // needs translation
    'contributors.empty_heading' => '', // needs translation
    'contributors.empty_body' => '', // needs translation
    'contributors.explore_language' => '', // needs translation
    'contributors.all_contributors' => '', // needs translation
    'contributors.not_found' => '', // needs translation
    'contributors.not_found_message' => '', // needs translation
    'contributors.browse_all' => '', // needs translation

    // Oral Histories
    'oral_histories.title' => 'Gdibaajmowinaanin', // dict: dibaajmowin: "story, narrative"; g- (our) + -aanin plural
    'oral_histories.subtitle' => '', // needs translation
    'oral_histories.collections' => '', // needs translation
    'oral_histories.stories' => 'Dibaajimowinan', // dict: dibaajmowin: "a story; a narrative" (plural)
    'oral_histories.not_found' => '', // needs translation
    'oral_histories.not_found_message' => '', // needs translation
    'oral_histories.browse_all' => '', // needs translation

    // User Menu
    'usermenu.open' => '', // needs translation
    'usermenu.messages' => '', // needs translation
    'usermenu.profile' => '', // needs translation
    'usermenu.dashboard' => '', // needs translation
    'usermenu.sign_out' => '', // needs translation
    'usermenu.log_in' => '', // needs translation
];

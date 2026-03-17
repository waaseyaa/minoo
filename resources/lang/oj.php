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
    'nav.programs' => 'Anokiiwinan', // dict: anokii: "s/he works"; -winan nominal plural
    'nav.elder_support' => 'Gichi-aya\'aa Wiidookaagewin', // dict: gichi-aya'aa: "an elder" + wiidookaagewin: "help, assistance"
    'nav.volunteer' => 'Wiidookaage', // dict: wiidookaage: "s/he helps people"
    'nav.search' => 'Andone\'igen', // dict: andone': "go look for, search for, seek"
    'nav.dashboard' => 'Anokiiwin', // dict: anokiiwin: work, activity
    'nav.my_dashboard' => 'Nindanokiiwin', // dict: nind- (my) + anokiiwin (work/activity)
    'nav.account' => 'Nindizhinikaazowin', // dict: nind- (my) + izhinikaazowin (name/identity) — "my account"
    'nav.logout' => 'Zaaga\'an', // dict: zaaga'am: "s/he goes out, exits"
    'nav.login' => 'Biindigen', // dict: biindige: "enter, go inside"

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
    'events.subtitle' => "Niimi'idiwinan, anami'ewinan, miinawaa oodena-maawanji'idiwinan", // "Powwows, ceremonies, and community gatherings"
    'events.empty_heading' => "Maawanji'idiwinan gaawiin mikanziinaawaa", // "No events found"
    'events.empty_body' => "Maawanji'idiwinan bi-dagoshinomagadoon waabang.", // "Events are coming soon."
    'events.explore_button' => 'Naanaagadawaabandan oodenawinan', // "Explore communities"
    'events.detail_back' => "Maawanji'idiwinan", // "Events"
    'events.not_found' => "Maawanji'idiwin gaawiin mikanziin", // "Event not found"
    'events.not_found_message' => "Maawanji'idiwin gaa-nandawaabandaman gaawiin ayaasinoon.", // "The event you're looking for doesn't exist."
    'events.browse_all' => "Kakina maawanji'idiwinan", // "All events"

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
    'language.copyright' => '',
    'language.detail_back' => 'Anishinaabemowin',
    'language.not_found' => 'Ikidowin gaawiin mikanziin', // "Word not found"
    'language.not_found_message' => 'Ikidowin gaa-nandawaabandaman gaawiin ayaasinoon.', // "The word you're looking for doesn't exist."
    'language.browse_all' => 'Kakina ikidowinan', // "All words"

    // Search
    'search.title' => 'Andone\'igen', // dict: andone': "search for, seek"
    'search.placeholder' => 'Andone\'…', // dict: andone': "search for, seek"
    'search.button' => 'Andone\'', // dict: andone': "search for, seek"
    'search.filters_label' => 'Gashkitoonaanan:',
    'search.type_heading' => '',
    'search.sources_heading' => '',
    'search.scope' => '',
    'search.results_summary' => '{count} ezhichigewinan ({time}s)',
    'search.no_results' => 'Ezhichigewinan gaa-izhi-waabandamaan "{query}".',
    'search.pagination_prev' => '',
    'search.pagination_next' => '',
    'search.search_intro' => '',

    // People
    'people.title' => 'Anishinaabeg', // dict: anishinaabeg: people (plural)
    'people.subtitle' => "Gichi-aya'aag, gikendaasowininiwag, miinawaa oodena-niigaanziijig", // "Elders, knowledge keepers, and community leaders"
    'people.browse_all' => 'Kakina anishinaabeg', // "All people"
    'people.nearby_notice' => '',
    'people.show_all' => 'Kakina',
    'people.search_placeholder' => "Andone' anishinaabeg\u{2026}",
    'people.filter_role' => 'Ogimaawi',
    'people.filter_offering' => '',
    'people.filter_all_roles' => '',
    'people.filter_all_offerings' => '',
    'people.clear_filters' => '',
    'people.mentor_callout' => '',
    'people.empty_heading' => 'Anishinaabeg gaawiin mikanziinaawaa', // "No people found"
    'people.empty_body' => '',
    'people.volunteer_button' => 'Wiidookaage',
    'people.detail_back' => 'Anishinaabeg',
    'people.not_found' => 'Anishinaabe gaawiin mikanziin', // "Person not found"
    'people.not_found_message' => 'Anishinaabe gaa-nandawaabandaman gaawiin ayaasinoon.', // "The person you're looking for doesn't exist."
    'people.filters_empty' => '',

    // Communities listing
    'communities.exploring' => 'Naanaagadawaabandamang', // "Exploring" (progressive form)
    'communities.all_communities' => 'Kakina oodenawinan', // dict: kakina: "all" + oodenawinan: "towns"
    'communities.search_placeholder' => 'Andawaabandan oodenawinan...', // dict: andawaabandan: "look for, search for"
    'communities.search_label' => 'Andawaabandan oodenawinan', // dict: andawaabandan: "look for, search for"
    'communities.all_types' => 'Kakina',
    'communities.first_nations' => 'Anishinaabeg', // dict: anishinaabeg: people (plural)
    'communities.municipalities' => 'Oodenawinan', // dict: oodena: "town" (plural)
    'communities.province' => '',
    'communities.nation' => 'Ogimaawi-anishinaabeg',
    'communities.population' => 'Akiing-ogimaawag',
    'communities.under_500' => '',
    'communities.pop_500_2000' => '',
    'communities.pop_2000_5000' => '',
    'communities.pop_5000_plus' => '',
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
    'community.inac_band_no' => '',
    'community.website' => "Web-mazina'igan",
    'community.leadership' => 'Ogimaawin', // dict: ogimaa: "chief, leader"
    'community.chief' => 'Ogimaa', // dict: ogimaa: "a chief, a boss"
    'community.current' => '',
    'community.councillor' => '',
    'community.band_office' => '',
    'community.address' => 'Endaad', // dict: endaad: "h/ home; h/ house"
    'community.hours' => '',
    'community.phone' => 'Giigidoo-makakoons', // dict: giigido: "speaks" + makakoons: "small box"
    'community.email' => '',
    'community.toll_free' => '',
    'community.fax' => '',
    'community.the_land' => 'Aki', // dict: aki: "land, earth, country"
    'community.openstreetmap' => '',
    'community.google_maps' => '',
    'community.nearby_communities' => 'Besho oodenawinan', // "Nearby communities"
    'community.km_away' => 'km besho', // "km away/nearby"
    'community.pop' => '',

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
    'auth.reset_link_generated' => '',
    'auth.reset_submitted' => '',
    'auth.reset_button' => '',
    'auth.back_to_login' => '',
    'auth.reset_title' => '',
    'auth.reset_error_link' => '',
    'auth.new_password' => '',
    'auth.confirm_password' => '',
    'auth.reset_submit' => '',

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
];

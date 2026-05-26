<?php
// File: index.php - Quaid-e-Azam Group of Colleges - Main Landing Page
// Backend integration placeholders - connect to your existing db.php

// Uncomment when integrating with backend:
// require_once 'config/db.php';
// $database = new Database();
// $db = $database->getConnection();

require_once __DIR__ . '/includes/site-stats.php';

$public_nav_active = 'home';
$public_brand_subtitle = 'Group of Colleges';
$public_footer_campus_url = 'campus-rajanpur.php';
$public_footer_portals = true;
$public_nav_search = true;

$hero_slides = [
    [
        'key'      => 'rajanpur',
        'tag'      => 'MAIN CAMPUS · RAJANPUR',
        'headline' => 'Where Futures<br>Are <em>Built</em> Every Day',
        'tagline'  => 'Pakistan\'s most trusted group of colleges — shaping confident, capable and career-ready graduates since 2015.',
    ],
    [
        'key'      => 'fazilpur',
        'tag'      => 'HAMID CAMPUS · FAZILPUR',
        'headline' => 'Small Campus.<br><em>Limitless</em> Ambition.',
        'tagline'  => 'Intimate learning environment with world-class academic programs — where every student gets the attention they deserve.',
    ],
    [
        'key'      => 'kotmithan',
        'tag'      => 'ABUL REHMAN CAMPUS · KOT MITHAN',
        'headline' => 'Rising from Kot Mithan.<br><em>Reaching</em> Every Dream.',
        'tagline'  => 'Quality education brought closer to home — for every ambitious student who dares to dream bigger.',
    ],
];
foreach ($hero_slides as &$slide) {
    $cs = $campus_stats[$slide['key']];
    $slide['image'] = $cs['image'];
    $slide['url']   = $cs['url'];
    $slide['students'] = $cs['students'];
    $slide['faculty']  = $cs['faculty'];
    $slide['programs'] = $cs['programs'];
}
unset($slide);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quaid-e-Azam Group of Colleges – Excellence in Education since 2015. Misbah, Hamid & Abul Rehman Campuses across South Punjab.">
    <meta name="keywords" content="Quaid-e-Azam College, QAC, Rajanpur College, Fazilpur College, Kot Mithan College, Admissions 2026, FSc, ICS, BSCS, NAVTTC">
    <meta name="author" content="Quaid-e-Azam Group of Colleges">
    <meta property="og:title" content="Quaid-e-Azam Group of Colleges">
    <meta property="og:description" content="Excellence in Education since 2015 — Rajanpur, Fazilpur, Kot Mithan">
    <meta property="og:image" content="assets/images/qgc-logo.png">
    <meta name="theme-color" content="#2ab5a0">
    <meta http-equiv="Cache-Control" content="max-age=31536000">
    <title>Quaid-e-Azam Group of Colleges | South Punjab</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/qgc-logo-nav.png">
    <link rel="apple-touch-icon" href="assets/images/qgc-logo-nav.png">
    <link rel="preload" href="assets/images/qgc-logo-nav.png" as="image" fetchpriority="high">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" as="style" onload="this.rel='stylesheet'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    </noscript>

    <!-- Bootstrap 5 -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></noscript>

    <!-- Font Awesome -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"></noscript>

    <!-- AOS Animation -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link href="assets/css/public-site.css?v=3" rel="stylesheet">

    <style>
        
        :root {
            --teal:        #2ab5a0;
            --teal-dark:   #35a99c;
            --teal-light:  #a8e6e0;
            --teal-pale:   #e8faf8;
            --navy:        #0f2d48;
            --navy-mid:    #1a4060;
            --navy-light:  #2a5f82;
            --gold:        #f0b429;
            --gold-light:  #fde68a;
            --white:       #ffffff;
            --gray-50:     #f8fafc;
            --gray-100:    #f1f5f9;
            --gray-200:    #e2e8f0;
            --gray-400:    #94a3b8;
            --gray-600:    #475569;
            --gray-800:    #1e293b;
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body:    'DM Sans', system-ui, sans-serif;
            --font-mono:    'Space Mono', 'Courier New', monospace;
            --radius-sm:   8px;
            --radius-md:   16px;
            --radius-lg:   24px;
            --shadow-sm:   0 2px 8px rgba(15,45,72,.08);
            --shadow-md:   0 8px 32px rgba(15,45,72,.14);
            --shadow-lg:   0 20px 60px rgba(15,45,72,.18);
            --transition:  all .35s cubic-bezier(.4,0,.2,1);
        }

        
        *, *::before, *::after { box-sizing: border-box; }
        html {
            scroll-behavior: smooth;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: var(--font-body);
            color: var(--gray-800);
            background: var(--white);
            overflow-x: hidden;
            line-height: 1.7;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .lazy-section {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .lazy-section.visible {
            opacity: 1;
            transform: translateY(0);
        }

        
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--navy); }
        ::-webkit-scrollbar-thumb { background: var(--teal); border-radius: 99px; }

        
        .campus-banner {
            background: var(--navy);
            color: rgba(255,255,255,.7);
            font-family: var(--font-mono);
            font-size: .72rem;
            letter-spacing: .08em;
            padding: 7px 0;
            border-bottom: 1px solid rgba(78,194,181,.15);
        }
        .campus-banner a {
            color: var(--teal-light);
            text-decoration: none;
            transition: var(--transition);
        }
        .campus-banner a:hover { color: var(--teal); }

        .campus-switcher {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(78,194,181,.1);
            border: 1px solid rgba(78,194,181,.25);
            border-radius: 99px;
            padding: 3px 12px 3px 6px;
            font-size: .7rem;
        }
        .campus-switcher-label {
            color: rgba(255,255,255,.4);
            font-size: .64rem;
            margin-right: 4px;
        }
        .campus-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 99px;
            font-size: .68rem;
            letter-spacing: .05em;
            cursor: pointer;
            transition: var(--transition);
        }
        @media (max-width: 767px) {
            .campus-banner {
                font-size: .68rem;
                letter-spacing: .04em;
                padding: 8px 0;
            }
            .campus-banner .container > .d-flex {
                align-items: flex-start !important;
                gap: 8px !important;
            }
            .campus-banner .container > .d-flex > .d-flex:first-child {
                width: 100%;
                gap: 8px !important;
                min-width: 0;
            }
            .campus-banner .container > .d-flex > .d-flex:first-child span {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .campus-switcher {
                width: 100%;
                justify-content: flex-start;
                overflow-x: auto;
                gap: 6px;
                border-radius: 12px;
                padding: 6px;
                scrollbar-width: none;
                -webkit-overflow-scrolling: touch;
            }
            .campus-switcher::-webkit-scrollbar {
                display: none;
            }
            .campus-switcher-label {
                flex: 0 0 auto;
                align-self: center;
            }
            .campus-pill {
                flex: 0 0 auto;
                min-height: 30px;
                display: inline-flex;
                align-items: center;
                padding: 5px 10px;
                background: rgba(255,255,255,.06);
                border: 1px solid rgba(255,255,255,.08);
                white-space: nowrap;
            }
        }

        
        .navbar-main {
            background: rgba(10, 30, 55, 0.98) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(78,194,181,.2) !important;
            padding: 12px 0 !important;
            box-shadow: 0 4px 30px rgba(0,0,0,.3);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        
        .brand-logo {
            width: 58px !important;
            height: 58px !important;
            background: none !important;
            box-shadow: none !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-logo img {
            width: 58px !important;
            height: 58px !important;
            object-fit: contain !important;
            filter: drop-shadow(0 2px 8px rgba(78,194,181,.3));
        }

        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .brand-name {
            font-size: 1rem !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            line-height: 1.3 !important;
            display: block;
        }
        .brand-sub {
            color: #2ab5a0 !important;
            font-size: .65rem !important;
            letter-spacing: .1em !important;
            display: block;
            margin-top: 2px;
        }

        
        .nav-link-main {
            color: #ffffff !important;
            font-size: .88rem !important;
            font-weight: 500 !important;
            padding: 8px 14px !important;
            border-radius: 8px !important;
            transition: all .3s ease !important;
            opacity: 1 !important;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .nav-link-main:hover {
            color: #2ab5a0 !important;
            background: rgba(78,194,181,.1) !important;
        }
        .nav-link-main.active {
            color: #2ab5a0 !important;
            background: rgba(78,194,181,.1) !important;
        }

        
        .nav-search-btn {
            background: rgba(78,194,181,.15) !important;
            border: 1px solid rgba(78,194,181,.3) !important;
            color: #2ab5a0 !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .nav-search-btn:hover {
            background: #2ab5a0 !important;
            color: #0f2d48 !important;
        }

        
        .btn-portal-fancy {
            background: linear-gradient(135deg, #2ab5a0, #35a99c) !important;
            color: #0f2d48 !important;
            font-weight: 700 !important;
            font-size: .85rem !important;
            padding: 10px 22px !important;
            border-radius: 99px !important;
            box-shadow: 0 4px 20px rgba(78,194,181,.35) !important;
            border: none !important;
            white-space: nowrap !important;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            position: relative;
        }
        .btn-portal-fancy:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 30px rgba(78,194,181,.55) !important;
            color: #0f2d48 !important;
        }
        .portal-dot {
            width: 8px;
            height: 8px;
            background: #f0b429;
            border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 0 rgba(240,180,41,0.7); }
            50% { transform: scale(1.2); opacity: 0.8; box-shadow: 0 0 0 10px rgba(240,180,41,0); }
        }

        
        .nav-dropdown-wrap {
            position: relative;
        }
        .nav-dropdown {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            background: rgba(10,30,55,.98) !important;
            border: 1px solid rgba(78,194,181,.2) !important;
            box-shadow: 0 20px 60px rgba(0,0,0,.4) !important;
            border-radius: 12px;
            padding: 8px;
            min-width: 260px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .nav-dropdown-wrap:hover .nav-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(5px);
        }
        .nav-dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 8px;
            text-decoration: none;
            color: rgba(255,255,255,.8);
            transition: all 0.2s ease;
        }
        .nav-dropdown-item:hover {
            background: rgba(78,194,181,.1) !important;
            color: #2ab5a0;
        }
        .dropdown-title {
            color: #ffffff !important;
            font-weight: 600 !important;
            font-size: 0.9rem;
            display: block;
        }
        .dropdown-sub {
            color: rgba(255,255,255,.5) !important;
            font-size: 0.75rem;
            display: block;
        }
        .nav-dropdown-footer {
            display: block;
            text-align: center;
            padding: 10px;
            border-top: 1px solid rgba(78,194,181,.1);
            margin-top: 4px;
            color: #2ab5a0;
            font-size: 0.8rem;
            text-decoration: none;
        }

        
        .navbar-divider {
            width: 1px;
            height: 36px;
            background: rgba(78,194,181,.2);
            margin: 0 16px;
        }

        
        .search-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 30, 55, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 100px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .search-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            max-width: 700px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(78,194,181,0.3);
            border-radius: 20px;
            padding: 20px 30px;
        }
        .search-box input {
            flex: 1;
            background: none;
            border: none;
            outline: none;
            color: #fff;
            font-size: 1.2rem;
        }
        .search-box input::placeholder { color: rgba(255,255,255,0.3); }
        .search-box button {
            background: none;
            border: none;
            color: #2ab5a0;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .search-results {
            width: 100%;
            max-width: 700px;
            margin-top: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        .search-result-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(78,194,181,0.1);
            border-radius: 15px;
            text-decoration: none;
            color: #fff;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }
        .search-result-item:hover {
            background: rgba(78,194,181,0.1);
            transform: translateX(5px);
        }
        .search-result-type {
            font-size: 0.7rem;
            color: #2ab5a0;
            background: rgba(78,194,181,0.1);
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
            font-weight: 700;
        }
        .search-no-result {
            text-align: center;
            color: rgba(255,255,255,0.5);
            padding: 40px;
        }


        .btn-hero-primary {
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            color: var(--navy);
            font-weight: 700;
            font-size: .9rem;
            letter-spacing: .04em;
            padding: 14px 32px;
            border-radius: 99px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            box-shadow: 0 8px 32px rgba(78,194,181,.4);
        }
        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 40px rgba(78,194,181,.55);
            color: var(--navy);
        }

        .btn-hero-secondary {
            color: var(--white);
            font-weight: 500;
            font-size: .9rem;
            padding: 14px 28px;
            border-radius: 99px;
            border: 1px solid rgba(255,255,255,.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            backdrop-filter: blur(8px);
        }
        .btn-hero-secondary:hover {
            border-color: var(--teal);
            color: var(--teal);
            background: rgba(78,194,181,.06);
        }

        
        section { padding: 90px 0; }
        .section-alt { background: var(--gray-50); }

        .section-badge {
            display: inline-block;
            background: var(--teal-pale);
            color: var(--teal-dark);
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 99px;
            margin-bottom: 14px;
            border: 1px solid rgba(78,194,181,.3);
        }
        .section-title {
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 900;
            color: var(--navy);
            line-height: 1.15;
            letter-spacing: -.02em;
        }
        .section-title .teal { color: var(--teal-dark); }
        .section-subtitle {
            color: var(--gray-600);
            font-size: .95rem;
            max-width: 580px;
            line-height: 1.8;
            margin-top: 12px;
        }

        
        .program-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 34px 28px;
            height: 100%;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .program-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--teal), var(--teal-dark));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .4s ease;
        }
        .program-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: transparent; }
        .program-card:hover::before { transform: scaleX(1); }

        .program-icon {
            width: 58px; height: 58px;
            background: var(--teal-pale);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--teal-dark);
            margin-bottom: 20px;
            transition: var(--transition);
        }
        .program-card:hover .program-icon {
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            color: var(--white);
            transform: rotate(-5deg) scale(1.1);
        }
        .program-name {
            font-family: var(--font-display);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 10px;
        }
        .program-desc { color: var(--gray-600); font-size: .88rem; line-height: 1.7; margin-bottom: 20px; }
        .programs-compact .program-desc,
        .programs-compact .program-meta,
        .programs-compact .program-subhead-meta,
        .programs-compact .section-subtitle {
            display: none;
        }
        .programs-compact .program-card {
            padding: 28px 24px;
        }
        .program-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .program-tag {
            font-family: var(--font-mono);
            font-size: .64rem;
            letter-spacing: .06em;
            color: var(--teal-dark);
            background: var(--teal-pale);
            border: 1px solid rgba(78,194,181,.25);
            padding: 3px 10px;
            border-radius: 99px;
        }
        .program-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--teal-dark);
            font-weight: 600;
            font-size: .83rem;
            text-decoration: none;
            margin-top: 18px;
            transition: var(--transition);
        }
        .program-link:hover { gap: 10px; color: var(--navy); }

        .hero-bridge {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            padding: 28px 0;
        }
        .hero-bridge-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .hero-bridge-text {
            color: var(--gray-600);
            font-size: .92rem;
            margin: 0;
            max-width: 520px;
        }
        .hero-bridge-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 28px;
        }
        .hero-bridge-stat strong {
            display: block;
            font-family: var(--font-display);
            font-size: 1.35rem;
            font-weight: 900;
            color: var(--navy);
            line-height: 1;
        }
        .hero-bridge-stat span {
            font-family: var(--font-mono);
            font-size: .62rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--gray-400);
            margin-top: 6px;
            display: block;
        }

        .program-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 32px;
        }
        .program-tab-btn {
            border: 1px solid var(--gray-200);
            background: var(--white);
            color: var(--navy);
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 99px;
            cursor: pointer;
            transition: var(--transition);
        }
        .program-tab-btn:hover {
            border-color: var(--teal);
            color: var(--teal-dark);
        }
        .program-tab-btn.active {
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            border-color: transparent;
            color: var(--navy);
        }
        .program-tab-panel { display: none; }
        .program-tab-panel.active { display: block; }
        .program-subhead {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .program-subhead-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .program-subhead-icon.gold {
            background: linear-gradient(135deg, var(--gold), #e0a020);
        }
        .program-subhead-icon.teal {
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
        }
        .program-subhead-icon i { color: var(--navy); font-size: 1.1rem; }
        .program-subhead-title {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--navy);
        }
        .program-subhead-meta {
            font-family: var(--font-mono);
            font-size: .62rem;
            color: var(--teal-dark);
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .program-tag.inter { color: #8a5e00; background: #fff3cc; border-color: rgba(240,180,41,.3); }
        .program-card .program-icon.inter {
            background: linear-gradient(135deg, #fff9ec, var(--gold-light));
        }
        .program-card .program-icon.inter i { color: #b07800; }

        .navttc-section { background: var(--navy); position: relative; overflow: hidden; }
        .navttc-section .navttc-grid-bg {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(78,194,181,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(78,194,181,.04) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black, transparent);
            pointer-events: none;
        }
        .navttc-card {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(78,194,181,.12);
            border-radius: var(--radius-lg);
            padding: 32px 26px;
            height: 100%;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .navttc-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--teal), var(--teal-dark));
            border-radius: 3px 3px 0 0;
        }
        .navttc-card:hover {
            background: rgba(78,194,181,.07);
            border-color: rgba(78,194,181,.3);
            transform: translateY(-6px);
        }
        .navttc-card-icon {
            width: 58px;
            height: 58px;
            background: rgba(78,194,181,.12);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--teal);
            margin-bottom: 20px;
        }
        .navttc-card-name {
            font-family: var(--font-display);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 6px;
        }
        .navttc-card-full {
            font-family: var(--font-mono);
            font-size: .65rem;
            color: rgba(255,255,255,.4);
            letter-spacing: .06em;
            margin-bottom: 18px;
        }
        .navttc-card-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        .navttc-enroll {
            margin-top: auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--teal);
            font-weight: 600;
            font-size: .83rem;
            text-decoration: none;
            transition: var(--transition);
        }
        .navttc-enroll:hover { gap: 12px; color: var(--white); }

        
        .why-section { background: var(--navy); position: relative; overflow: hidden; }
        .why-section::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 50%;
            height: 100%;
            background: radial-gradient(ellipse at top right, rgba(78,194,181,.08) 0%, transparent 65%);
            pointer-events: none;
        }

        .feature-item {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            padding: 28px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(78,194,181,.1);
            transition: var(--transition);
            background: rgba(255,255,255,.02);
        }
        .feature-item:hover {
            background: rgba(78,194,181,.06);
            border-color: rgba(78,194,181,.25);
            transform: translateX(6px);
        }
        .feature-icon-wrap {
            width: 50px; height: 50px;
            background: rgba(78,194,181,.12);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: var(--teal);
            flex-shrink: 0;
            transition: var(--transition);
        }
        .feature-item:hover .feature-icon-wrap {
            background: var(--teal);
            color: var(--navy);
        }
        .feature-title { font-family: var(--font-display); font-size: 1.05rem; font-weight: 700; color: var(--white); margin-bottom: 8px; }
        .feature-desc { color: rgba(255,255,255,.55); font-size: .85rem; line-height: 1.7; margin: 0; }

        
        .stats-banner {
            background: linear-gradient(135deg, var(--teal), var(--teal-dark) 60%, #1a8a7e);
            padding: 70px 0;
        }
        .stat-box { text-align: center; }
        .stat-number {
            font-family: var(--font-display);
            font-size: clamp(2.2rem, 5vw, 3.5rem);
            font-weight: 900;
            color: var(--navy);
            line-height: 1;
            display: block;
        }
        .stat-divider {
            width: 1px;
            background: rgba(15,45,72,.15);
            align-self: stretch;
        }
        .stat-label {
            font-family: var(--font-mono);
            font-size: .7rem;
            color: rgba(15,45,72,.65);
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-top: 8px;
            display: block;
        }

        
        .admissions-cta {
            background: var(--navy);
            position: relative;
            overflow: hidden;
        }
        .admissions-cta::after {
            content: '"';
            position: absolute;
            bottom: -60px;
            right: 5%;
            font-family: var(--font-display);
            font-size: 28rem;
            color: rgba(78,194,181,.04);
            line-height: 1;
            pointer-events: none;
        }
        .admissions-card {
            background: linear-gradient(135deg, var(--navy-mid), var(--navy-light));
            border: 1px solid rgba(78,194,181,.2);
            border-radius: var(--radius-lg);
            padding: 50px;
            position: relative;
            overflow: hidden;
        }
        .admissions-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(ellipse at top left, rgba(78,194,181,.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .btn-apply {
            background: var(--gold);
            color: var(--navy);
            font-weight: 700;
            font-size: .95rem;
            letter-spacing: .04em;
            padding: 16px 40px;
            border-radius: 99px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            box-shadow: 0 8px 30px rgba(240,180,41,.35);
        }
        .btn-apply:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 40px rgba(240,180,41,.5);
            color: var(--navy);
        }

        .deadline-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(240,180,41,.12);
            border: 1px solid rgba(240,180,41,.3);
            border-radius: 99px;
            padding: 6px 16px;
            font-family: var(--font-mono);
            font-size: .7rem;
            color: var(--gold-light);
            letter-spacing: .06em;
            margin-bottom: 20px;
        }

        
        .news-card {
            background: var(--white);
            border: 1px solid rgba(15,45,72,.08);
            border-radius: var(--radius-md);
            overflow: hidden;
            height: 100%;
            position: relative;
            transition: var(--transition);
            box-shadow: 0 14px 34px rgba(15,45,72,.08);
            transform-style: preserve-3d;
        }
        .news-card:hover {
            transform: translateY(-9px) perspective(900px) rotateX(1.5deg);
            box-shadow: 0 28px 70px rgba(15,45,72,.18);
            border-color: rgba(78,194,181,.28);
        }
        .news-card-img {
            width: 100%;
            aspect-ratio: 16/9;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #0f2d48, #2ab5a0);
            isolation: isolate;
        }
        .news-card-img::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                linear-gradient(180deg, rgba(15,45,72,0) 45%, rgba(15,45,72,.34) 100%),
                radial-gradient(circle at 18% 12%, rgba(78,194,181,.38), transparent 34%);
            pointer-events: none;
        }
        .news-card-img::after {
            content: "";
            position: absolute;
            inset: 14px;
            z-index: 2;
            border: 1px solid rgba(255,255,255,.32);
            border-radius: 12px;
            box-shadow: inset 0 0 30px rgba(255,255,255,.10);
            pointer-events: none;
            opacity: .75;
        }
        .news-card-img img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transform: scale(1.02);
            filter: saturate(1.04) contrast(1.03);
            transition: transform .7s ease, filter .7s ease;
        }
        .news-card:hover .news-card-img img {
            transform: scale(1.1) translateY(-2px);
            filter: saturate(1.12) contrast(1.06);
        }
        .news-card-body { padding: 24px; }
        .news-date {
            font-family: var(--font-mono);
            font-size: .64rem;
            color: var(--teal-dark);
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .news-title {
            font-family: var(--font-display);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 10px;
            line-height: 1.35;
        }
        .news-excerpt { color: var(--gray-600); font-size: .84rem; line-height: 1.7; }
        .news-link {
            color: var(--teal-dark);
            font-weight: 600;
            font-size: .83rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 16px;
            transition: var(--transition);
        }
        .news-link:hover { gap: 9px; color: var(--navy); }
        .content-hidden { display: none; }
        .load-more-wrap {
            margin-top: 28px;
            text-align: center;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-md);
            aspect-ratio: 4/3;
            border: 1px solid rgba(15,45,72,.08);
            box-shadow: var(--shadow-sm);
            background: var(--gray-100);
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .6s ease;
        }
        .gallery-item:hover img {
            transform: scale(1.06);
        }
        @media (max-width: 991px) {
            .gallery-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 575px) {
            .gallery-grid { grid-template-columns: 1fr; }
        }

        
        .campus-card {
            border-radius: var(--radius-lg);
            overflow: hidden;
            position: relative;
            aspect-ratio: 4/3;
            cursor: pointer;
            display: block;
            text-decoration: none;
        }
        .campus-card-bg {
            position: absolute;
            inset: 0;
            transition: transform .5s ease;
        }
        .campus-card:hover .campus-card-bg { transform: scale(1.06); }
        .campus-card-bg {
            background-size: cover;
            background-position: center;
        }
        .campus-card-rajanpur .campus-card-bg {
            background-image: linear-gradient(160deg, rgba(15, 45, 72, 0.72), rgba(42, 91, 130, 0.55)), url('assets/images/image1.png');
        }
        .campus-card-fazilpur .campus-card-bg {
            background-image: linear-gradient(160deg, rgba(13, 61, 79, 0.78), rgba(26, 111, 138, 0.55)), url('assets/images/image2.png');
        }
        .campus-card-kotmithan .campus-card-bg {
            background-image: linear-gradient(160deg, rgba(26, 61, 47, 0.78), rgba(45, 110, 78, 0.55)), url('assets/images/image3.png');
        }
        .campus-card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(10,25,45,.9) 30%, transparent);
        }
        .campus-card-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px;
            transform: translateY(0);
            transition: var(--transition);
        }
        .campus-card-tag {
            font-family: var(--font-mono);
            font-size: .62rem;
            letter-spacing: .1em;
            color: var(--teal);
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .campus-card-name {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--white);
            line-height: 1.2;
        }
        .campus-card-info {
            color: rgba(255,255,255,.6);
            font-size: .82rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .campus-arrow {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px; height: 40px;
            background: rgba(78,194,181,.15);
            border: 1px solid rgba(78,194,181,.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--teal);
            transition: var(--transition);
        }
        .campus-card:hover .campus-arrow { background: var(--teal); color: var(--navy); }
        .campus-tab-panel { display: none; }
        .campus-tab-panel.active { display: block; }
        .campus-tabs {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 28px;
        }

        
        .footer {
            background: var(--gray-800);
            color: rgba(255,255,255,.6);
            padding: 70px 0 0;
        }
        .footer-brand { margin-bottom: 20px; }
        .footer-brand-name {
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--white);
        }
        .footer-brand-sub {
            font-family: var(--font-mono);
            font-size: .62rem;
            color: var(--teal);
            letter-spacing: .1em;
        }
        .footer-desc { font-size: .85rem; line-height: 1.8; max-width: 280px; }
        .footer-heading {
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--teal);
            margin-bottom: 20px;
        }
        .footer-links { list-style: none; padding: 0; margin: 0; }
        .footer-links li { margin-bottom: 10px; }
        .footer-links a {
            color: rgba(255,255,255,.55);
            text-decoration: none;
            font-size: .85rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .footer-links a:hover { color: var(--teal); transform: translateX(4px); }
        .footer-links a i { width: 14px; font-size: .7rem; }
        .footer-contact { font-size: .85rem; line-height: 2; }
        .footer-contact i { color: var(--teal); width: 16px; margin-right: 8px; }

        .footer-bottom {
            background: rgba(0,0,0,.2);
            border-top: 1px solid rgba(255,255,255,.06);
            padding: 20px 0;
            margin-top: 50px;
            font-size: .78rem;
            font-family: var(--font-mono);
            letter-spacing: .03em;
        }
        .social-links { display: flex; gap: 10px; }
        .social-link {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: rgba(255,255,255,.06);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,.5);
            text-decoration: none;
            transition: var(--transition);
            font-size: .85rem;
        }
        .social-link:hover { background: var(--teal); color: var(--navy); transform: translateY(-3px); }

        
        .ticker-wrap {
            background: linear-gradient(90deg, var(--teal-dark), #1a8a7e);
            padding: 10px 0;
            overflow: hidden;
        }
        .ticker-label {
            background: var(--navy);
            color: var(--teal);
            font-family: var(--font-mono);
            font-size: .65rem;
            letter-spacing: .1em;
            padding: 3px 14px;
            border-radius: 99px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .ticker-content {
            overflow: hidden;
            flex: 1;
            mask-image: linear-gradient(90deg, transparent, black 5%, black 95%, transparent);
        }
        .ticker-track {
            display: flex;
            gap: 60px;
            animation: ticker 30s linear infinite;
            white-space: nowrap;
        }
        .ticker-item {
            color: var(--navy);
            font-size: .82rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .ticker-item i { opacity: .7; }
        @keyframes ticker { from { transform: translateX(0); } to { transform: translateX(-50%); } }

        
        .navbar-toggler { border-color: rgba(78,194,181,.4); }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(78,194,181,0.8)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        
        #campusHero {
          padding: 0;
          background: #0a1628;
          overflow: hidden;
        }
            .qs-hero-wrap {
                    position: relative;
                    width: 100%;
                    height: 100vh;
                    min-height: 64vh;
                    max-height: 920px;
                    background: #0a1628;
                    overflow: hidden;
                    display: flex;
                    align-items: center;
                }

                @media (max-width: 768px) {
                    .qs-hero-wrap { min-height: 56vh; }
                }
                @media (max-width: 420px) {
                    .qs-hero-wrap { min-height: 48vh; }
                }
        .qs-grid-bg {
          position: absolute;
          inset: 0;
          background-image:
            linear-gradient(rgba(42,181,160,0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(42,181,160,0.04) 1px, transparent 1px);
          background-size: 60px 60px;
          pointer-events: none;
          opacity: 0.55;
        }
        .qs-top-bar {
          position: absolute;
          top: 0; left: 0; right: 0;
          height: 3px;
          background: #2ab5a0;
          z-index: 10;
        }
        .qs-left-label {
          position: absolute;
          left: 16px;
          top: 50%;
          transform: translateY(-50%) rotate(-90deg);
          font-size: 9px;
          letter-spacing: 0.2em;
          color: rgba(255,255,255,0.18);
          white-space: nowrap;
          text-transform: uppercase;
          z-index: 5;
        }
        .qs-slide-indicator {
          position: absolute;
          top: 22px;
          right: 28px;
          display: flex;
          gap: 8px;
          align-items: center;
          z-index: 10;
        }
        .qs-dot {
          width: 6px; height: 6px;
          border-radius: 50%;
          background: rgba(255,255,255,0.25);
          transition: all 0.3s ease;
        }
        .qs-dot.active {
          width: 22px;
          border-radius: 3px;
          background: #2ab5a0;
        }
        .qs-slide {
          position: absolute;
          inset: 0;
          display: flex;
          align-items: center;
          overflow: hidden;
          opacity: 0;
          visibility: hidden;
          transform: translateY(16px);
          transition: opacity 0.7s ease, transform 0.7s ease, visibility 0.7s ease;
          z-index: 1;
        }
        .qs-slide::before {
          content: "";
          position: absolute;
          inset: 0;
          z-index: 1;
          background:
            linear-gradient(90deg, rgba(6,16,31,0.96) 0%, rgba(6,16,31,0.78) 38%, rgba(6,16,31,0.42) 68%, rgba(6,16,31,0.82) 100%),
            radial-gradient(circle at 74% 48%, rgba(42,181,160,0.20), transparent 34%);
          pointer-events: none;
        }
        .qs-slide::after {
          content: "";
          position: absolute;
          inset: 0;
          z-index: 2;
          background-image:
            linear-gradient(rgba(42,181,160,0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(42,181,160,0.05) 1px, transparent 1px);
          background-size: 60px 60px;
          pointer-events: none;
          opacity: 0.62;
        }
                .qs-slide-bg {
                    position: absolute;
                    inset: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 0;
                    background-image: none;
                    background-size: cover;
                    background-position: center;
                    object-fit: cover;
                    display: block;
                    opacity: 0;
                    transform: scale(1.02);
                    transition: opacity 0.7s ease, transform 7s ease;
                    filter: saturate(1.05) contrast(1.04);
                    pointer-events: none;
                }
        .qs-slide.active {
          opacity: 1;
          visibility: visible;
          transform: translateY(0);
          z-index: 2;
        }
        .qs-slide.active .qs-slide-bg {
          opacity: 1;
          transform: scale(1.08);
        }
        .qs-content {
          position: relative;
          z-index: 3;
          width: 100%;
          max-width: 720px;
          margin: 0 auto;
          padding: 0 80px;
          display: flex;
          align-items: center;
        }
        .qs-left {
          display: flex;
          flex-direction: column;
          gap: 16px;
        }
        .qs-campus-tag {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          background: rgba(42,181,160,0.1);
          border: 1px solid rgba(42,181,160,0.28);
          padding: 5px 14px;
          border-radius: 999px;
          width: fit-content;
        }
        .qs-campus-tag span {
          font-size: 9px;
          letter-spacing: 0.18em;
          color: #2ab5a0;
          font-weight: 700;
          text-transform: uppercase;
        }
        .qs-live-dot {
          width: 6px; height: 6px;
          border-radius: 50%;
          background: #2ab5a0;
          animation: qsPulse 1.6s infinite;
        }
        @keyframes qsPulse {
          0%,100% { opacity: 1; }
          50% { opacity: 0.3; }
        }
        .qs-headline {
          font-size: clamp(2rem, 3.5vw, 3.2rem);
          font-weight: 800;
          color: #ffffff;
          line-height: 1.12;
          letter-spacing: -0.02em;
          margin: 0;
        }
        .qs-headline em {
          color: #2ab5a0;
          font-style: normal;
        }
        .qs-tagline {
          font-size: 14px;
          color: rgba(255,255,255,0.5);
          line-height: 1.7;
          max-width: 400px;
          margin: 0;
        }
        .qs-stats-row {
          display: flex;
          align-items: center;
          gap: 20px;
        }
        .qs-stat { display: flex; flex-direction: column; gap: 2px; }
        .qs-stat-num {
          font-size: 22px;
          font-weight: 800;
          color: #ffffff;
          line-height: 1;
        }
        .qs-stat-label {
          font-size: 9px;
          color: rgba(255,255,255,0.35);
          text-transform: uppercase;
          letter-spacing: 0.1em;
        }
        .qs-stat-div {
          width: 1px;
          height: 36px;
          background: rgba(255,255,255,0.1);
        }
        .qs-cta-row {
          display: flex;
          gap: 12px;
          flex-wrap: wrap;
        }
        .qs-btn-primary {
          background: #2ab5a0;
          color: #ffffff;
          padding: 13px 26px;
          border-radius: 6px;
          font-size: 13px;
          font-weight: 700;
          text-decoration: none;
          transition: background 0.2s, transform 0.2s;
          display: inline-block;
        }
        .qs-btn-primary:hover {
          background: #33c8b1;
          transform: translateY(-2px);
          color: #fff;
          text-decoration: none;
        }
        .qs-btn-ghost {
          background: transparent;
          color: rgba(255,255,255,0.7);
          padding: 13px 22px;
          border-radius: 6px;
          font-size: 13px;
          font-weight: 600;
          border: 1px solid rgba(255,255,255,0.18);
          text-decoration: none;
          transition: background 0.2s, color 0.2s;
          display: inline-block;
        }
        .qs-btn-ghost:hover {
          background: rgba(255,255,255,0.07);
          color: #fff;
          text-decoration: none;
        }
        .qs-tab-switcher {
          position: absolute;
          bottom: 22px;
          left: 80px;
          display: flex;
          gap: 6px;
          z-index: 10;
        }
        .qs-tab {
          font-size: 10px;
          color: rgba(255,255,255,0.3);
          padding: 5px 14px;
          border-radius: 999px;
          border: 1px solid transparent;
          cursor: pointer;
          transition: all 0.25s ease;
        }
        .qs-tab.active {
          color: #2ab5a0;
          border-color: rgba(42,181,160,0.3);
          background: rgba(42,181,160,0.08);
        }
        .qs-nav-arrows {
          position: absolute;
          bottom: 16px;
          right: 28px;
          display: flex;
          gap: 8px;
          z-index: 10;
        }
        .qs-arrow {
          width: 36px; height: 36px;
          border-radius: 50%;
          border: 1px solid rgba(255,255,255,0.15);
          background: transparent;
          color: rgba(255,255,255,0.5);
          font-size: 18px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.2s ease;
          line-height: 1;
        }
        .qs-arrow:hover {
          border-color: #2ab5a0;
          color: #2ab5a0;
        }
        .qs-progress-bar {
          position: absolute;
          bottom: 0; left: 0;
          height: 3px;
          background: #2ab5a0;
          width: 0%;
          z-index: 10;
          transition: width 5s linear;
        }
        @media (max-width: 991px) {
          .qs-content { padding: 0 40px; }
          .qs-left-label { display: none; }
          .qs-tab-switcher { left: 40px; }
        }
        @media (max-width: 576px) {
          .qs-content { padding: 0 24px; }
          .qs-headline { font-size: 1.9rem; }
          .qs-tab-switcher { left: 24px; }
        }
        .divider-teal { width: 48px; height: 3px; background: linear-gradient(90deg, var(--teal), var(--teal-dark)); border-radius: 99px; margin: 16px 0 24px; }

        @media (max-width: 768px) {
            .admissions-card { padding: 30px 24px; }
        }

        
        .navbar-main > .container > .d-flex {
            flex-wrap: nowrap;
            min-width: 0;
        }
        .nav-link-main,
        .dropdown-trigger {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
            line-height: 1;
        }
        .nav-link-main.active {
            box-shadow: inset 0 -2px 0 #2ab5a0;
            background: transparent !important;
            border-radius: 0 !important;
        }
        .btn-portal-fancy {
            white-space: nowrap;
            flex: 0 0 auto;
            min-height: 44px;
            line-height: 1;
            padding: 10px 18px !important;
            gap: 9px;
        }
        @media (max-width: 1199px) {
            .brand-name { font-size: .9rem !important; }
            .brand-logo,
            .brand-logo img { width: 50px !important; height: 50px !important; }
            .nav-link-main { font-size: .82rem !important; padding: 8px 9px !important; }
            .btn-portal-fancy { font-size: .8rem !important; padding: 10px 14px !important; }
        }
        @media (max-width: 991px) {
            .nav-link-main {
                justify-content: flex-start;
                width: 100%;
                border-radius: 8px !important;
            }
            .nav-link-main.active { box-shadow: none; }
            .btn-portal-fancy { width: auto; }
        }
        @media (max-width: 991px) {
            .hero,
            #campusHero,
            .qgc-hero {
                min-height: 100vh;
                position: relative;
            }
            .hero::before,
            #campusHero::before,
            .qgc-hero::before {
                content: '';
                position: absolute;
                inset: 0;
                background: rgba(15,45,72,0.75);
                z-index: 1;
                pointer-events: none;
            }
            .qs-hero-wrap {
                min-height: 100vh;
                height: auto;
                max-height: none;
                align-items: stretch;
            }
            .hero-content,
            .qs-content {
                position: relative;
                z-index: 3;
                text-align: center;
                padding: 100px 16px 92px;
                max-width: 100%;
                min-height: 100vh;
                justify-content: center;
            }
            .qs-left {
                width: 100%;
                align-items: center;
            }
            .qs-slide {
                align-items: stretch;
            }
            .qs-slide::before {
                background: rgba(15,45,72,0.75);
            }
            .hero-visual,
            .hero-image-frame {
                display: none !important;
            }
            .col-lg-6 {
                width: 100% !important;
            }
            .navbar-collapse {
                background: #0f2d48;
                padding: 16px;
                border-radius: 12px;
                margin-top: 8px;
                border: 1px solid rgba(78,194,181,.15);
            }
            .nav-link,
            .nav-link-campus {
                padding: 10px 0 !important;
                border-bottom: 1px solid rgba(255,255,255,.05);
            }
            .portal-menu,
            .campus-dropdown-menu {
                position: static !important;
                opacity: 1 !important;
                visibility: visible !important;
                transform: none !important;
                box-shadow: none !important;
                border: 1px solid rgba(78,194,181,.15);
                margin-top: 8px;
            }
        }
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }
            .container {
                padding-left: 16px;
                padding-right: 16px;
            }
            section {
                padding: 60px 0 !important;
            }
            .card {
                margin-bottom: 16px;
            }
            .campus-tags,
            .hero-campuses,
            .qs-tab-switcher {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                justify-content: center;
                padding: 0 16px;
                margin-top: 16px;
            }
            .qs-tab-switcher {
                left: 50%;
                right: auto;
                bottom: 20px;
                transform: translateX(-50%);
                width: 100%;
                max-width: 360px;
                z-index: 11;
            }
            .campus-tag,
            .qs-campus-tag,
            .qs-tab {
                font-size: .7rem;
                padding: 5px 12px;
                border-radius: 99px;
                background: rgba(78,194,181,.15);
                border: 1px solid rgba(78,194,181,.3);
                color: #fff;
                white-space: nowrap;
            }
            .qs-campus-tag {
                width: auto;
                margin: 0 auto;
            }
            .qs-campus-tag span {
                color: #fff;
                font-size: .7rem;
                letter-spacing: .08em;
            }
            .hero-stats,
            .qs-stats-row {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px;
                border: none;
                background: transparent;
                margin-top: 24px;
            }
            .qs-stat-div {
                display: none;
            }
            .hero-stat,
            .qs-stat {
                background: rgba(255,255,255,.08);
                border: 1px solid rgba(78,194,181,.2);
                border-radius: 12px;
                padding: 12px 16px;
                min-width: 80px;
                text-align: center;
                flex: 0 0 auto;
            }
            .hero-stat-num,
            .qs-stat-num {
                font-size: 1.3rem;
                font-weight: 700;
                color: #4ec2b5;
                display: block;
            }
            .hero-stat-label,
            .qs-stat-label {
                font-size: .65rem;
                color: rgba(255,255,255,.5);
                text-transform: uppercase;
                letter-spacing: .05em;
            }
        }
        @media (max-width: 576px) {
            .hero-cta-group,
            .qs-cta-row {
                display: flex;
                flex-direction: column;
                gap: 10px;
                width: 100%;
                align-items: center;
                margin-top: 24px;
            }
            .btn-hero-primary,
            .btn-hero-secondary,
            .qs-btn-primary,
            .qs-btn-ghost {
                width: 100%;
                max-width: 280px;
                text-align: center;
                justify-content: center;
                padding: 14px 24px;
                font-size: .95rem;
            }
            .hero-headline,
            .qs-headline {
                font-size: 2rem !important;
                line-height: 1.2;
                margin-bottom: 12px;
            }
            .hero-tagline,
            .qs-tagline {
                font-size: .85rem;
            }
            .hero-desc {
                font-size: .82rem;
                line-height: 1.6;
            }
            .qs-content {
                padding: 96px 16px 108px;
            }
            .qs-slide-indicator,
            .qs-nav-arrows {
                display: none;
            }
        }
    </style>
</head>
<body class="modern-ui">
<div id="page-loader" style="position:fixed;inset:0;background:#0f2d48;display:flex;align-items:center;justify-content:center;z-index:9999;">
    <div style="width:40px;height:40px;border:3px solid rgba(78,194,181,.3);border-top-color:#4ec2b5;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
</div>

<!-- ─── Campus Top Banner ──────────────────────────────────────── -->
<div class="campus-banner">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-map-marker-alt" style="color:var(--teal);font-size:.7rem;"></i>
                <span>Three campuses across South Punjab</span>
                <span class="text-white-50 d-none d-md-inline">|</span>
                <span class="d-none d-md-inline"><i class="fas fa-phone" style="color:var(--teal);"></i> &nbsp;<?= htmlspecialchars($site_phone) ?></span>
            </div>
            <div class="campus-switcher">
                <span class="campus-switcher-label">CAMPUS:</span>
                <a href="campus-rajanpur.php" class="campus-pill">Misbah Campus</a>
                <a href="campus-fazilpur.php" class="campus-pill">Hamid Campus</a>
                <a href="campus-kotmithan.php" class="campus-pill">Abul Rehman Campus</a>
            </div>
        </div>
    </div>
</div>

<!-- ─── Announcement Ticker ───────────────────────────────────── -->
<div class="ticker-wrap">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <span class="ticker-label">LATEST</span>
            <div class="ticker-content">
                <div class="ticker-track">
                    <?php
                    // Replace with real DB query: $announcements = $db->query("SELECT title FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();
                    $announcements = [
                        ['title' => 'Admissions Open 2026 - Apply Now for FA, FSc & ICS Programs', 'link' => 'modules/admissions/apply.php'],
                        ['title' => 'Annual Examination Schedule Published - Check Student Portal', 'link' => 'modules/examination/index.php'],
                        ['title' => 'Merit Lists for Batch 2026 will be displayed on 15 June 2026', 'link' => 'modules/student/index.php'],
                        ['title' => 'Fee Submission Deadline: 30 May 2026 - Avoid Late Charges', 'link' => 'modules/fee_management/index.php'],
                        ['title' => 'Inter-Campus Sports Gala - Rajanpur Campus, 20 June 2026', 'link' => 'news.php'],
                    ];
                    // Duplicate for seamless loop
                    $all = array_merge($announcements, $announcements);
                    foreach ($all as $ann): ?>
                    <span class="ticker-item">
                        <i class="fas fa-bullhorn"></i>
                        <?= htmlspecialchars($ann['title']) ?>
                        &nbsp;&nbsp;•&nbsp;&nbsp;
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/public-nav.php'; ?>


<!-- ─── HERO ──────────────────────────────────────────────────── -->
<section class="college-hero qgc-hero" id="campusHero">
    <div class="qs-hero-wrap">
      <div class="qs-grid-bg"></div>
      <div class="qs-top-bar"></div>
      <div class="qs-left-label">Quaid-e-Azam Group of Colleges</div>

      <div class="qs-slide-indicator">
        <div class="qs-dot active"></div>
        <div class="qs-dot"></div>
        <div class="qs-dot"></div>
      </div>

      <?php foreach ($hero_slides as $i => $slide): ?>
            <div class="qs-slide<?= $i === 0 ? ' active' : '' ?>">
                <img class="qs-slide-bg" src="<?= htmlspecialchars($slide['image']) ?>" alt="" width="1200" height="800" loading="lazy">
        <div class="qs-content">
          <div class="qs-left">
            <div class="qs-campus-tag">
              <div class="qs-live-dot"></div>
              <span><?= htmlspecialchars($slide['tag']) ?></span>
            </div>
            <h1 class="qs-headline"><?= $slide['headline'] ?></h1>
            <p class="qs-tagline"><?= htmlspecialchars($slide['tagline']) ?></p>
            <div class="qs-stats-row">
              <div class="qs-stat"><span class="qs-stat-num"><?= htmlspecialchars($slide['students']) ?></span><span class="qs-stat-label">Students</span></div>
              <div class="qs-stat-div"></div>
              <div class="qs-stat"><span class="qs-stat-num"><?= htmlspecialchars($slide['faculty']) ?></span><span class="qs-stat-label">Faculty</span></div>
              <div class="qs-stat-div"></div>
              <div class="qs-stat"><span class="qs-stat-num"><?= htmlspecialchars($slide['programs']) ?></span><span class="qs-stat-label">Programs</span></div>
            </div>
            <div class="qs-cta-row">
              <a href="modules/admissions/apply.php" class="qs-btn-primary">Apply Now 2026</a>
              <a href="<?= htmlspecialchars($slide['url']) ?>" class="qs-btn-ghost">Explore Campus →</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Bottom controls -->
      <div class="qs-tab-switcher">
        <?php foreach ($hero_slides as $i => $slide):
            $tab_label = $campus_stats[$slide['key']]['name'];
            if ($slide['key'] === 'kotmithan') {
                $tab_label = 'Abul Rehman';
            }
        ?>
        <div class="qs-tab<?= $i === 0 ? ' active' : '' ?>" data-slide="<?= $i ?>"><?= htmlspecialchars($tab_label) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="qs-nav-arrows">
        <button class="qs-arrow" id="qsPrev">&#8249;</button>
        <button class="qs-arrow" id="qsNext">&#8250;</button>
      </div>
      <div class="qs-progress-bar" id="qsProgress"></div>
    </div>
</section>

<!-- ─── HERO BRIDGE ───────────────────────────────────────────── -->
<div class="hero-bridge">
    <div class="container">
        <div class="hero-bridge-inner" data-aos="fade-up">
            <p class="hero-bridge-text">Three campuses across South Punjab — intermediate, degree, and NAVTTC skill programs under one trusted institution.</p>
            <div class="hero-bridge-stats">
                <div class="hero-bridge-stat"><strong><?= $total_students ?></strong><span>Network Students</span></div>
                <div class="hero-bridge-stat"><strong><?= $total_faculty ?></strong><span>Faculty</span></div>
                <div class="hero-bridge-stat"><strong><?= $total_campuses ?></strong><span>Campuses</span></div>
            </div>
        </div>
    </div>
</div>

<!-- ─── STATS BANNER ──────────────────────────────────────────── -->
<div class="stats-banner" id="stats">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col text-center" data-aos="zoom-in" data-aos-delay="0">
                <div class="stat-box">
                    <span class="stat-number"><?= $total_students ?></span>
                    <span class="stat-label">Enrolled Students</span>
                </div>
            </div>
            <div class="col-auto d-none d-md-block"><div class="stat-divider" style="height:70px;"></div></div>
            <div class="col text-center" data-aos="zoom-in" data-aos-delay="100">
                <div class="stat-box">
                    <span class="stat-number"><?= $total_faculty ?></span>
                    <span class="stat-label">Expert Faculty</span>
                </div>
            </div>
            <div class="col-auto d-none d-md-block"><div class="stat-divider" style="height:70px;"></div></div>
            <div class="col text-center" data-aos="zoom-in" data-aos-delay="200">
                <div class="stat-box">
                    <span class="stat-number"><?= $total_campuses ?></span>
                    <span class="stat-label">Campuses</span>
                </div>
            </div>
            <div class="col-auto d-none d-md-block"><div class="stat-divider" style="height:70px;"></div></div>
            <div class="col text-center" data-aos="zoom-in" data-aos-delay="300">
                <div class="stat-box">
                    <span class="stat-number"><?= $pass_rate ?></span>
                    <span class="stat-label">Board Pass Rate</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── WHY QAC ───────────────────────────────────────────────── -->
<section class="why-section lazy-section" id="why-qac">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5" data-aos="fade-right">
                <span class="section-badge" style="background:rgba(78,194,181,.1);border-color:rgba(78,194,181,.2);">Why Choose Us</span>
                <h2 class="section-title" style="color:var(--white);">The QGC <span style="color:var(--teal);">Advantage</span></h2>
                <div class="divider-teal"></div>
                <p class="section-subtitle" style="color:rgba(255,255,255,.6);max-width:420px;">
                    Since <?= $established_year ?>, Quaid-e-Azam Group of Colleges has been a trusted choice for
                    students across South Punjab seeking quality intermediate and degree education.
                </p>
                <div class="mt-4">
                    <a href="modules/admissions/apply.php" class="btn-hero-primary">
                        Start Your Application <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php
                    $features = [
                        ['icon'=>'fa-chalkboard-teacher','title'=>'Expert Faculty','desc'=>'Qualified teachers with strong board examination experience and dedicated student mentorship.'],
                        ['icon'=>'fa-laptop-code','title'=>'Digital Classrooms','desc'=>'Smart boards, LMS portal, and computer labs equipped with latest technology for modern learning.'],
                        ['icon'=>'fa-medal','title'=>'Proven Board Results','desc'=>'Consistently strong outcomes in BISE DG Khan examinations since our founding in ' . $established_year . '.'],
                        ['icon'=>'fa-shield-alt','title'=>'Safe Campus','desc'=>'Secure, CCTV-monitored premises with dedicated wardens ensuring a disciplined study environment.'],
                        ['icon'=>'fa-flask','title'=>'Modern Labs','desc'=>'Fully equipped Biology, Chemistry, Physics and Computer Science laboratories for hands-on learning.'],
                        ['icon'=>'fa-hand-holding-heart','title'=>'Need-Based Scholarships','desc'=>'Merit and need-based financial assistance to ensure no deserving student is left behind.'],
                    ];
                    foreach ($features as $i => $f): ?>
                    <div class="col-md-6" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
                        <div class="feature-item">
                            <div class="feature-icon-wrap">
                                <i class="fas <?= $f['icon'] ?>"></i>
                            </div>
                            <div>
                                <div class="feature-title"><?= htmlspecialchars($f['title']) ?></div>
                                <p class="feature-desc"><?= htmlspecialchars($f['desc']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── PROGRAMS (tabbed) ─────────────────────────────────────── -->
<section id="programs" class="section-alt lazy-section programs-compact">
    <div class="container">
        <div class="row align-items-end mb-4">
            <div class="col-lg-8" data-aos="fade-right">
                <span class="section-badge">Academic Programs</span>
                <h2 class="section-title">Programs That <span class="teal">Define Careers</span></h2>
                <div class="divider-teal"></div>
                <p class="section-subtitle">Intermediate programs affiliated with BISE DG Khan and degree programs with Islamia University of Bahawalpur — plus NAVTTC-certified short courses.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0" data-aos="fade-left">
                <a href="programs.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill" style="font-size:.85rem;border-color:var(--gray-200);">
                    Full Program List <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>

        <div class="program-tabs" data-aos="fade-up" role="tablist">
            <button type="button" class="program-tab-btn active" data-program-tab="intermediate" role="tab" aria-selected="true">Intermediate</button>
            <button type="button" class="program-tab-btn" data-program-tab="degree" role="tab" aria-selected="false">Degree</button>
            <a href="#navttc-courses" class="program-tab-btn" style="text-decoration:none;">NAVTTC Skills</a>
        </div>

        <div class="program-tab-panel active" id="program-panel-intermediate" role="tabpanel">
            <div class="program-subhead" data-aos="fade-up">
                <div class="program-subhead-icon gold"><i class="fas fa-graduation-cap"></i></div>
                <div>
                    <div class="program-subhead-title">Intermediate Programs</div>
                    <div class="program-subhead-meta">Affiliated with BISE DG Khan</div>
                </div>
            </div>
            <div class="row g-4">
                <?php
                $inter_programs = [
                    ['icon'=>'fa-flask',      'name'=>'FSc Pre-Medical',      'desc'=>'For students aspiring to become doctors, dentists and health professionals.',             'tags'=>['2 Years','BISE DG Khan']],
                    ['icon'=>'fa-calculator', 'name'=>'FSc Pre-Engineering',  'desc'=>'For future engineers, architects and technology professionals.',                           'tags'=>['2 Years','BISE DG Khan']],
                    ['icon'=>'fa-desktop',    'name'=>'ICS Computer Science', 'desc'=>'For students entering the world of software, IT and digital technology.',                 'tags'=>['2 Years','BISE DG Khan']],
                    ['icon'=>'fa-briefcase',  'name'=>'I.Com Commerce',       'desc'=>'For future businessmen, accountants and finance professionals.',                          'tags'=>['2 Years','BISE DG Khan']],
                    ['icon'=>'fa-book-open',  'name'=>'FA Arts',              'desc'=>'For students pursuing law, journalism, civil services and social sciences.',            'tags'=>['2 Years','BISE DG Khan']],
                    ['icon'=>'fa-mosque',     'name'=>'Taleem-ul-Islam',      'desc'=>'For students seeking Islamic education integrated with modern academics.',                'tags'=>['2 Years','BISE DG Khan']],
                ];
                foreach ($inter_programs as $i => $ip): ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $i * 60 ?>">
                    <div class="program-card">
                        <div class="program-icon inter"><i class="fas <?= $ip['icon'] ?>"></i></div>
                        <div class="program-name"><?= htmlspecialchars($ip['name']) ?></div>
                        <p class="program-desc"><?= htmlspecialchars($ip['desc']) ?></p>
                        <div class="program-meta">
                            <?php foreach ($ip['tags'] as $tag): ?>
                            <span class="program-tag inter"><?= $tag ?></span>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?= program_apply_url($ip['name']) ?>" class="program-link">Apply Now <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="program-tab-panel" id="program-panel-degree" role="tabpanel">
            <div class="program-subhead" data-aos="fade-up">
                <div class="program-subhead-icon teal"><i class="fas fa-university"></i></div>
                <div>
                    <div class="program-subhead-title">Degree Programs</div>
                    <div class="program-subhead-meta">Affiliated with Islamia University of Bahawalpur</div>
                </div>
            </div>
            <div class="row g-4">
                <?php
                $degree_programs = [
                    ['icon'=>'fa-palette',       'name'=>'ADP Arts',                  'desc'=>'Two year associate degree in arts for students pursuing humanities and social sciences.',     'tags'=>['2 Years','IUB Affiliated']],
                    ['icon'=>'fa-atom',          'name'=>'ADP Science',               'desc'=>'Two year associate degree in science for students in applied and natural sciences.',         'tags'=>['2 Years','IUB Affiliated']],
                    ['icon'=>'fa-laptop-code',   'name'=>'BSCS Computer Science',     'desc'=>'Four year degree in computer science, software engineering and programming.',              'tags'=>['4 Years','IUB Affiliated']],
                    ['icon'=>'fa-network-wired', 'name'=>'BS Information Technology', 'desc'=>'Four year degree focused on networks, systems and modern IT solutions.',                   'tags'=>['4 Years','IUB Affiliated']],
                    ['icon'=>'fa-microscope',    'name'=>'BS Zoology',                'desc'=>'Four year degree in biological and life sciences for future researchers.',                  'tags'=>['4 Years','IUB Affiliated']],
                    ['icon'=>'fa-infinity',      'name'=>'BS Mathematics',            'desc'=>'Four year degree in pure and applied mathematics for analytical careers.',                 'tags'=>['4 Years','IUB Affiliated']],
                    ['icon'=>'fa-language',      'name'=>'BS Urdu',                   'desc'=>'Four year degree in Urdu language, literature and linguistics.',                           'tags'=>['4 Years','IUB Affiliated']],
                    ['icon'=>'fa-vial',          'name'=>'BS Chemistry',              'desc'=>'Four year degree in chemical sciences for research and industry careers.',                 'tags'=>['4 Years','IUB Affiliated']],
                ];
                foreach ($degree_programs as $i => $dp): ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $i * 60 ?>">
                    <div class="program-card">
                        <div class="program-icon"><i class="fas <?= $dp['icon'] ?>"></i></div>
                        <div class="program-name"><?= htmlspecialchars($dp['name']) ?></div>
                        <p class="program-desc"><?= htmlspecialchars($dp['desc']) ?></p>
                        <div class="program-meta">
                            <?php foreach ($dp['tags'] as $tag): ?>
                            <span class="program-tag"><?= $tag ?></span>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?= program_apply_url($dp['name']) ?>" class="program-link">Apply Now <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ─── NAVTTC SHORT COURSES ──────────────────────────────────────── -->
<section id="navttc-courses" class="navttc-section lazy-section">
    <div class="navttc-grid-bg"></div>
    <div style="position:absolute;top:-10%;right:-5%;width:50vw;height:50vw;background:radial-gradient(ellipse at center,rgba(78,194,181,.08) 0%,transparent 65%);border-radius:50%;pointer-events:none;"></div>

    <div class="container" style="position:relative;z-index:2;padding-top:90px;padding-bottom:90px;">
        <!-- Section Header -->
        <div class="row align-items-end mb-5">
            <div class="col-lg-8" data-aos="fade-right">
                <span class="section-badge" style="background:rgba(78,194,181,.1);border-color:rgba(78,194,181,.2);color:var(--teal);">National Vocational &amp; Technical Training Commission</span>
                <h2 class="section-title" style="color:var(--white);">NAVTTC <span style="color:var(--teal);">Short Courses</span></h2>
                <div class="divider-teal"></div>
                <p class="section-subtitle" style="color:rgba(255,255,255,.6);">Quaid-e-Azam Group of Colleges in collaboration with NAVTTC offers short courses of 3 months and 6 months duration to equip students with modern professional skills for today's job market.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0" data-aos="fade-left">
                <div style="display:inline-flex;align-items:center;gap:12px;background:rgba(78,194,181,.08);border:1px solid rgba(78,194,181,.2);border-radius:var(--radius-md);padding:14px 20px;">
                    <i class="fas fa-certificate" style="color:var(--teal);font-size:1.4rem;"></i>
                    <div>
                        <div style="font-family:var(--font-mono);font-size:.62rem;color:rgba(255,255,255,.5);letter-spacing:.08em;text-transform:uppercase;">Certification</div>
                        <div style="font-family:var(--font-display);font-weight:700;color:var(--white);font-size:.95rem;">Govt. Certified</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Cards -->
        <div class="row g-4 mb-5">
            <?php
            $navttc_courses = [
                ['icon'=>'fa-computer',      'name'=>'CCA',                      'full'=>'Computer Applications',     'duration'=>'3 Months'],
                ['icon'=>'fa-code',          'name'=>'Web Development',          'full'=>'Full Stack Web Dev',        'duration'=>'3 Months'],
                ['icon'=>'fa-pen-nib',       'name'=>'Graphic Designing',        'full'=>'Creative & Visual Design',  'duration'=>'3 Months'],
                ['icon'=>'fa-bullhorn',      'name'=>'Digital Marketing & SEO',  'full'=>'Online Growth Strategy',    'duration'=>'6 Months'],
                ['icon'=>'fa-object-group',  'name'=>'UX/UI Design',             'full'=>'User Experience Design',    'duration'=>'6 Months'],
                ['icon'=>'fa-robot',         'name'=>'Artificial Intelligence',  'full'=>'AI & Machine Learning',     'duration'=>'6 Months'],
                ['icon'=>'fa-music',         'name'=>'Vibe Coding',              'full'=>'AI-Assisted Programming',   'duration'=>'3 Months'],
            ];
            foreach ($navttc_courses as $i => $nc):
                $navttc_apply = program_apply_url('NAVTTC - ' . $nc['name']);
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
                <div class="navttc-card">
                    <div class="navttc-card-icon"><i class="fas <?= $nc['icon'] ?>"></i></div>
                    <div class="navttc-card-name"><?= htmlspecialchars($nc['name']) ?></div>
                    <div class="navttc-card-full"><?= htmlspecialchars($nc['full']) ?></div>
                    <div class="navttc-card-badges">
                        <span style="font-family:var(--font-mono);font-size:.62rem;letter-spacing:.07em;color:var(--navy);background:var(--teal);border-radius:99px;padding:4px 12px;font-weight:700;">
                            <i class="fas fa-clock" style="margin-right:4px;"></i><?= $nc['duration'] ?>
                        </span>
                        <span style="font-family:var(--font-mono);font-size:.62rem;letter-spacing:.07em;color:var(--teal);background:rgba(78,194,181,.1);border:1px solid rgba(78,194,181,.25);border-radius:99px;padding:4px 12px;">
                            <i class="fas fa-certificate" style="margin-right:4px;"></i>Govt Certified
                        </span>
                    </div>
                    <a href="<?= $navttc_apply ?>" class="navttc-enroll">Enroll Now <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Info Strip -->
        <div data-aos="fade-up" data-aos-delay="200">
            <div style="background:rgba(78,194,181,.06);border:1px solid rgba(78,194,181,.18);border-radius:var(--radius-lg);padding:28px 36px;">
                <div class="row g-4 align-items-center">
                    <div class="col-md-4">
                        <div style="display:flex;align-items:center;gap:14px;">
                            <div style="width:44px;height:44px;background:rgba(78,194,181,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-hourglass-half" style="color:var(--teal);font-size:1.1rem;"></i>
                            </div>
                            <div>
                                <div style="font-family:var(--font-mono);font-size:.6rem;color:rgba(255,255,255,.4);letter-spacing:.1em;text-transform:uppercase;margin-bottom:3px;">Duration</div>
                                <div style="font-family:var(--font-display);font-weight:700;color:var(--white);font-size:.95rem;">3 Months &amp; 6 Months</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="display:flex;align-items:center;gap:14px;">
                            <div style="width:44px;height:44px;background:rgba(78,194,181,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-award" style="color:var(--teal);font-size:1.1rem;"></i>
                            </div>
                            <div>
                                <div style="font-family:var(--font-mono);font-size:.6rem;color:rgba(255,255,255,.4);letter-spacing:.1em;text-transform:uppercase;margin-bottom:3px;">Certification</div>
                                <div style="font-family:var(--font-display);font-weight:700;color:var(--white);font-size:.95rem;">NAVTTC Government Certified</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="display:flex;align-items:center;gap:14px;">
                            <div style="width:44px;height:44px;background:rgba(78,194,181,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-user-check" style="color:var(--teal);font-size:1.1rem;"></i>
                            </div>
                            <div>
                                <div style="font-family:var(--font-mono);font-size:.6rem;color:rgba(255,255,255,.4);letter-spacing:.1em;text-transform:uppercase;margin-bottom:3px;">Eligibility</div>
                                <div style="font-family:var(--font-display);font-weight:700;color:var(--white);font-size:.95rem;">Matric and Above</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── ABOUT QAC ────────────────────────────────────────────── -->
<section id="about" class="lazy-section" style="background:var(--white);padding:90px 0;">

    <!-- ── CSS for this section only ── -->
    <style>
        
        .timeline { position: relative; padding-left: 0; }
        .timeline::before {
            content: '';
            position: absolute;
            left: 28px;
            top: 0; bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--teal), rgba(78,194,181,.1));
        }
        .timeline-item {
            display: flex;
            gap: 28px;
            align-items: flex-start;
            margin-bottom: 36px;
            position: relative;
        }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-dot {
            width: 58px;
            min-width: 58px;
            height: 58px;
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: .75rem;
            font-weight: 900;
            color: var(--navy);
            box-shadow: 0 4px 18px rgba(78,194,181,.35);
            z-index: 1;
            flex-shrink: 0;
        }
        .timeline-body {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-left: 3px solid var(--teal);
            border-radius: var(--radius-md);
            padding: 20px 24px;
            flex: 1;
            transition: var(--transition);
        }
        .timeline-body:hover {
            background: var(--teal-pale);
            border-color: var(--teal);
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }
        .timeline-year {
            font-family: var(--font-mono);
            font-size: .65rem;
            letter-spacing: .12em;
            color: var(--teal-dark);
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .timeline-text {
            color: var(--gray-600);
            font-size: .88rem;
            line-height: 1.75;
            margin: 0;
        }

        
        .leader-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 40px 32px;
            text-align: center;
            height: 100%;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .leader-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--teal), var(--teal-dark));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .4s ease;
        }
        .leader-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
        }
        .leader-card:hover::before { transform: scaleX(1); }
        .leader-avatar {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            margin: 0 auto 22px;
            border: 4px solid var(--teal-light);
            box-shadow: 0 0 0 6px rgba(78,194,181,.12);
            object-fit: cover;
            display: block;
        }
        .leader-avatar-fallback {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            margin: 0 auto 22px;
            border: 4px solid var(--teal-light);
            box-shadow: 0 0 0 6px rgba(78,194,181,.12);
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--navy);
        }
        .leader-name {
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 6px;
        }
        .leader-title {
            font-family: var(--font-mono);
            font-size: .65rem;
            letter-spacing: .12em;
            color: var(--teal-dark);
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .leader-desc {
            color: var(--gray-600);
            font-size: .87rem;
            line-height: 1.8;
        }
        .faculty-panel {
            display: none;
        }
        .faculty-panel.visible {
            display: flex;
        }
    </style>

    <div class="container">

        <!-- ── Section Header ── -->
        <div class="row justify-content-center mb-6" style="margin-bottom:56px;">
            <div class="col-lg-8 text-center" data-aos="fade-up">
                <span class="section-badge">Our Story</span>
                <h2 class="section-title">About <span class="teal">Quaid-e-Azam Group of Colleges</span></h2>
                <div class="divider-teal mx-auto"></div>
                <p class="section-subtitle mx-auto" style="max-width:680px;">
                    Quaid-e-Azam Group of Colleges has grown from a single campus in Rajanpur to a multi-campus
                    institution serving thousands of students across South Punjab. Affiliated with BISE DG Khan
                    for intermediate programs and Islamia University of Bahawalpur for degree programs, we are
                    committed to academic excellence, character building and modern skill development.
                </p>
            </div>
        </div>

        <div class="row g-5 align-items-start">

            <!-- ── Part 1: History Timeline ── -->
            <div class="col-lg-6" data-aos="fade-right">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div style="width:42px;height:42px;background:linear-gradient(135deg,var(--teal),var(--teal-dark));border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-history" style="color:var(--navy);font-size:1rem;"></i>
                    </div>
                    <div>
                        <div style="font-family:var(--font-display);font-weight:700;font-size:1.15rem;color:var(--navy);">Our Journey</div>
                        <div style="font-family:var(--font-mono);font-size:.6rem;color:var(--teal-dark);letter-spacing:.1em;text-transform:uppercase;">College History Timeline</div>
                    </div>
                </div>

                <div class="timeline">
                    <?php
                    $timeline = [
                        ['year'=>'2015','text'=>'Quaid-e-Azam Group of Colleges was established in Rajanpur, Punjab with a vision to provide quality intermediate education to the students of South Punjab.'],
                        ['year'=>'2017','text'=>'Fazilpur Campus was inaugurated as the second branch, extending quality education to the students of Fazilpur and surrounding areas.'],
                        ['year'=>'2018','text'=>'Kot Mithan Campus was established as the third branch, further expanding our reach across the region.'],
                        ['year'=>'2018','text'=>'NAVTTC Short Courses program was launched in collaboration with the National Vocational &amp; Technical Training Commission, offering 3 month and 6 month certified courses in modern skills.'],
                        ['year'=>'2024','text'=>'All three campuses achieved 100% pass rate in BISE DG Khan annual examinations — a landmark achievement for the institution.'],
                        ['year'=>'2026','text'=>'Over ' . $total_students . ' students enrolled across ' . $total_campuses . ' campuses with ' . $total_faculty . ' faculty members, cementing our position as a leading institution of South Punjab.'],
                    ];
                    foreach ($timeline as $i => $t): ?>
                    <div class="timeline-item" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
                        <div class="timeline-dot"><?= $t['year'] ?></div>
                        <div class="timeline-body">
                            <div class="timeline-year"><?= $t['year'] ?></div>
                            <p class="timeline-text"><?= $t['text'] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Part 2+3: Description + Leadership ── -->
            <div class="col-lg-6">

                <!-- Leadership header -->
                <div class="d-flex align-items-center gap-3 mb-4" data-aos="fade-left">
                    <div style="width:42px;height:42px;background:linear-gradient(135deg,var(--gold),#e0a020);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-users" style="color:var(--navy);font-size:1rem;"></i>
                    </div>
                    <div>
                        <div style="font-family:var(--font-display);font-weight:700;font-size:1.15rem;color:var(--navy);">Our Leadership</div>
                        <div style="font-family:var(--font-mono);font-size:.6rem;color:var(--teal-dark);letter-spacing:.1em;text-transform:uppercase;">The Team Behind Our Success</div>
                    </div>
                </div>
                <button type="button" class="program-tab-btn mb-4" id="facultyToggle" aria-expanded="false" aria-controls="facultyPanel">Show Leadership</button>

                <!-- Leadership Cards -->
                <div class="row g-4 faculty-panel" id="facultyPanel">
                    <?php
                    $leaders = [
                        [
                            'image'   => 'assets/images/saif shb (1).png',
                            'initial' => 'S',
                            'name'    => 'Ch. Saif Ullah',
                            'title'   => 'Chairman & Founder',
                            'desc'    => 'The visionary founder of Quaid-e-Azam Group of Colleges, Ch. Saif Ullah established this institution in 2015 with a mission to bring quality education to South Punjab.',
                        ],
                        [
                            'image'   => 'assets/images/zafar shb (2).png',
                            'initial' => 'Z',
                            'name'    => 'Mr. Zafar Iqbal',
                            'title'   => 'Principal',
                            'desc'    => 'With years of academic experience, Mr. Zafar Iqbal leads the college with dedication, ensuring the highest standards of education and discipline across all campuses.',
                        ],
                    ];
                    foreach ($leaders as $i => $leader): ?>
                    <div class="col-md-6" data-aos="fade-up" data-aos-delay="<?= $i * 120 ?>">
                        <div class="leader-card">
                            <?php if (file_exists($leader['image'])): ?>
                                <img src="<?= str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $leader['image']) ?>" alt="<?= htmlspecialchars($leader['name']) ?>" class="leader-avatar" loading="lazy" width="130" height="130">
                            <?php else: ?>
                                <div class="leader-avatar-fallback"><?= $leader['initial'] ?></div>
                            <?php endif; ?>
                            <div class="leader-name"><?= htmlspecialchars($leader['name']) ?></div>
                            <div class="leader-title"><?= htmlspecialchars($leader['title']) ?></div>
                            <p class="leader-desc"><?= htmlspecialchars($leader['desc']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Stats strip below cards -->
                <div class="row g-3 mt-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="col-4">
                        <div style="background:var(--teal-pale);border:1px solid rgba(78,194,181,.25);border-radius:var(--radius-md);padding:18px 12px;text-align:center;">
                            <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:900;color:var(--teal-dark);line-height:1;"><?= $total_campuses ?></div>
                            <div style="font-family:var(--font-mono);font-size:.6rem;color:var(--gray-600);letter-spacing:.08em;text-transform:uppercase;margin-top:5px;">Campuses</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="background:var(--teal-pale);border:1px solid rgba(78,194,181,.25);border-radius:var(--radius-md);padding:18px 12px;text-align:center;">
                            <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:900;color:var(--teal-dark);line-height:1;"><?= $years_active_display ?></div>
                            <div style="font-family:var(--font-mono);font-size:.6rem;color:var(--gray-600);letter-spacing:.08em;text-transform:uppercase;margin-top:5px;">Years Active</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="background:var(--teal-pale);border:1px solid rgba(78,194,181,.25);border-radius:var(--radius-md);padding:18px 12px;text-align:center;">
                            <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:900;color:var(--teal-dark);line-height:1;">100%</div>
                            <div style="font-family:var(--font-mono);font-size:.6rem;color:var(--gray-600);letter-spacing:.08em;text-transform:uppercase;margin-top:5px;">Pass Rate</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── ADMISSIONS CTA ────────────────────────────────────────── -->
<section class="admissions-cta lazy-section" id="admissions">
    <div class="container">
        <div class="admissions-card" data-aos="fade-up">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="deadline-chip">
                        <i class="fas fa-clock"></i>
                        ADMISSIONS OPEN · SESSION 2026–2028
                    </div>
                    <h2 class="section-title" style="color:var(--white);">Ready to Begin Your<br><span style="color:var(--teal);">Academic Journey?</span></h2>
                    <p style="color:rgba(255,255,255,.6);margin-top:16px;font-size:.95rem;max-width:560px;line-height:1.8;">
                        Secure your seat at Quaid-e-Azam Group of Colleges today. Limited seats available
                        across all programs. Online registration takes only 5 minutes.
                    </p>
                    <div class="row mt-4 g-3">
                        <div class="col-auto">
                            <div style="color:rgba(255,255,255,.5);font-family:var(--font-mono);font-size:.65rem;letter-spacing:.08em;margin-bottom:4px;">LAST DATE</div>
                            <div style="color:var(--white);font-family:var(--font-display);font-size:1.1rem;font-weight:700;">30 June 2026</div>
                        </div>
                        <div class="col-auto px-4" style="border-left:1px solid rgba(255,255,255,.1);">
                            <div style="color:rgba(255,255,255,.5);font-family:var(--font-mono);font-size:.65rem;letter-spacing:.08em;margin-bottom:4px;">FEE RANGE</div>
                            <div style="color:var(--white);font-family:var(--font-display);font-size:1.1rem;font-weight:700;">PKR 8,000 – 15,000</div>
                        </div>
                        <div class="col-auto px-4" style="border-left:1px solid rgba(255,255,255,.1);">
                            <div style="color:rgba(255,255,255,.5);font-family:var(--font-mono);font-size:.65rem;letter-spacing:.08em;margin-bottom:4px;">ELIGIBILITY</div>
                            <div style="color:var(--white);font-family:var(--font-display);font-size:1.1rem;font-weight:700;">Matric / O-Level</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="modules/admissions/apply.php" class="btn-apply d-inline-flex mb-3">
                        <i class="fas fa-file-alt"></i>
                        Apply Online Now
                    </a>
                    <br>
                    <a href="assets/downloads/prospectus.pdf" download="Quaid-e-Azam-College-Prospectus.pdf" class="btn-hero-secondary d-inline-flex" style="border-color:rgba(255,255,255,.15);">
                        <i class="fas fa-download"></i> Download Prospectus
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── CAMPUSES ──────────────────────────────────────────────── -->
<section id="campuses" class="section-alt lazy-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-badge">Our Locations</span>
            <h2 class="section-title">Three <span class="teal">Campuses,</span> One Vision</h2>
            <div class="divider-teal mx-auto"></div>
        </div>

        <div class="text-center">
            <div class="campus-tabs" role="tablist" aria-label="Campus details">
                <button type="button" class="program-tab-btn active" data-campus-tab="rajanpur" role="tab" aria-selected="true">Misbah Campus</button>
                <button type="button" class="program-tab-btn" data-campus-tab="fazilpur" role="tab" aria-selected="false">Hamid Campus</button>
                <button type="button" class="program-tab-btn" data-campus-tab="kotmithan" role="tab" aria-selected="false">Abul Rehman Campus</button>
            </div>
        </div>

        <div class="row g-4 justify-content-center">
            <!-- Rajanpur Campus -->
            <div class="col-lg-5 campus-tab-panel active" data-campus-panel="rajanpur" data-aos="fade-right">
                <a href="campus-rajanpur.php" class="campus-card campus-card-rajanpur d-block">
                    <div class="campus-card-bg">
                        <!-- Replace with: background-image: url(assets/images/rajanpur-campus.jpg); background-size: cover; -->
                    </div>
                    <div class="campus-card-overlay"></div>
                    <div class="campus-arrow"><i class="fas fa-arrow-right"></i></div>
                    <div class="campus-card-content">
                        <div class="campus-card-tag"><i class="fas fa-map-pin me-1"></i>Main Campus</div>
                        <div class="campus-card-name">Misbah<br>Campus</div>
                        <div class="campus-card-info">
                            <i class="fas fa-location-dot"></i>
                            <?= htmlspecialchars($campus_stats['rajanpur']['address']) ?>
                        </div>
                        <div class="d-flex gap-3 mt-3">
                            <span style="font-size:.72rem;color:rgba(255,255,255,.5);font-family:var(--font-mono);">
                                <i class="fas fa-users" style="color:var(--teal);"></i> <?= $campus_stats['rajanpur']['students'] ?> Students
                            </span>
                            <span style="font-size:.72rem;color:rgba(255,255,255,.5);font-family:var(--font-mono);">
                                <i class="fas fa-chalkboard" style="color:var(--teal);"></i> <?= $campus_stats['rajanpur']['programs'] ?> Programs
                            </span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Fazilpur Campus -->
            <div class="col-lg-5 campus-tab-panel" data-campus-panel="fazilpur" data-aos="fade-up">
                <a href="campus-fazilpur.php" class="campus-card campus-card-fazilpur d-block">
                    <div class="campus-card-bg">
                        <!-- Replace with: background-image: url(assets/images/fazilpur-campus.jpg); background-size: cover; -->
                    </div>
                    <div class="campus-card-overlay"></div>
                    <div class="campus-arrow"><i class="fas fa-arrow-right"></i></div>
                    <div class="campus-card-content">
                        <div class="campus-card-tag"><i class="fas fa-map-pin me-1"></i>Sub Campus</div>
                        <div class="campus-card-name">Hamid<br>Campus</div>
                        <div class="campus-card-info">
                            <i class="fas fa-location-dot"></i>
                            <?= htmlspecialchars($campus_stats['fazilpur']['address']) ?>
                        </div>
                        <div class="d-flex gap-3 mt-3">
                            <span style="font-size:.72rem;color:rgba(255,255,255,.5);font-family:var(--font-mono);">
                                <i class="fas fa-users" style="color:var(--teal);"></i> <?= $campus_stats['fazilpur']['students'] ?> Students
                            </span>
                            <span style="font-size:.72rem;color:rgba(255,255,255,.5);font-family:var(--font-mono);">
                                <i class="fas fa-chalkboard" style="color:var(--teal);"></i> <?= $campus_stats['fazilpur']['programs'] ?> Programs
                            </span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Kot Mithan Campus -->
            <div class="col-lg-5 campus-tab-panel" data-campus-panel="kotmithan" data-aos="fade-left">
                <a href="campus-kotmithan.php" class="campus-card campus-card-kotmithan d-block">
                    <div class="campus-card-bg">
                        <!-- Replace with: background-image: url(assets/images/kotmithan-campus.jpg); background-size: cover; -->
                    </div>
                    <div class="campus-card-overlay"></div>
                    <div class="campus-arrow"><i class="fas fa-arrow-right"></i></div>
                    <div class="campus-card-content">
                        <div class="campus-card-tag"><i class="fas fa-map-pin me-1"></i>Sub Campus</div>
                        <div class="campus-card-name">Abul Rehman<br>Campus</div>
                        <div class="campus-card-info">
                            <i class="fas fa-location-dot"></i>
                            <?= htmlspecialchars($campus_stats['kotmithan']['address']) ?>
                        </div>
                        <div class="d-flex gap-3 mt-3">
                            <span style="font-size:.72rem;color:rgba(255,255,255,.5);font-family:var(--font-mono);">
                                <i class="fas fa-users" style="color:var(--teal);"></i> <?= $campus_stats['kotmithan']['students'] ?> Students
                            </span>
                            <span style="font-size:.72rem;color:rgba(255,255,255,.5);font-family:var(--font-mono);">
                                <i class="fas fa-chalkboard" style="color:var(--teal);"></i> <?= $campus_stats['kotmithan']['programs'] ?> Programs
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ─── NEWS & UPDATES ────────────────────────────────────────── -->
<section id="news" class="lazy-section">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-7" data-aos="fade-right">
                <span class="section-badge">Latest Updates</span>
                <h2 class="section-title">News & <span class="teal">Announcements</span></h2>
                <div class="divider-teal"></div>
            </div>
            <div class="col-lg-5 text-lg-end" data-aos="fade-left">
                <a href="news.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill" style="font-size:.85rem;border-color:var(--gray-200);">
                    All News <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php
            // Replace with real DB query:
            // $news = $db->query("SELECT * FROM news_events WHERE is_published=1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
            $news = [
                ['date'=>'28 Apr 2026','title'=>'QAC Rajanpur Achieves 97% Pass Rate in BISE DG Khan Annual Exams','excerpt'=>'We are proud to announce that our students have achieved outstanding results in the annual board examinations...','icon'=>'fa-newspaper','slug'=>'board-results-2025'],
                ['date'=>'20 Apr 2026','title'=>'Annual Science Exhibition 2026 – Winners Announced','excerpt'=>'Students from both campuses showcased innovative projects at the Annual Science Exhibition held at Rajanpur Campus...','icon'=>'fa-flask','slug'=>'science-exhibition-2026'],
                ['date'=>'15 Apr 2026','title'=>'Admissions 2026-28 Open – Apply Before 30 June','excerpt'=>'Applications for the new academic session 2026-2028 are now open for all programs. Online and walk-in registration available...','icon'=>'fa-graduation-cap','slug'=>'admissions-2026'],
                ['date'=>'10 Apr 2026','title'=>'NAVTTC Short Courses Orientation Held Across Campuses','excerpt'=>'Students attended orientation sessions for practical skill programs, certification routes and employment-focused training...','icon'=>'fa-certificate','slug'=>'navttc-orientation'],
                ['date'=>'04 Apr 2026','title'=>'Parent Teacher Meeting Schedule Released','excerpt'=>'Campus offices have announced the spring parent teacher meeting schedule for academic progress review and mentoring...','icon'=>'fa-users','slug'=>'ptm-schedule'],
                ['date'=>'29 Mar 2026','title'=>'College Sports Week Concludes with Prize Distribution','excerpt'=>'Students participated in cricket, athletics and indoor competitions during an energetic inter-campus sports week...','icon'=>'fa-medal','slug'=>'sports-week'],
            ];
            foreach ($news as $i => $item): ?>
            <div class="col-lg-4 col-md-6 <?= $i >= 3 ? 'content-hidden' : '' ?>" data-load-more-item="news" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
                <div class="news-card">
                    <div class="news-card-img">
                        <img src="assets/images/image<?= ($i % 3) + 1 ?>.png" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy" width="640" height="360">
                    </div>
                    <div class="news-card-body">
                        <div class="news-date"><i class="fas fa-calendar me-1"></i><?= $item['date'] ?></div>
                        <div class="news-title"><?= htmlspecialchars($item['title']) ?></div>
                        <p class="news-excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>
                        <a href="news.php" class="news-link">
                            Read More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="load-more-wrap">
            <button type="button" class="program-tab-btn" data-load-more-button="news">Load More News</button>
        </div>
    </div>
</section>

<section id="gallery" class="section-alt lazy-section">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-8" data-aos="fade-right">
                <span class="section-badge">Campus Gallery</span>
                <h2 class="section-title">Life at <span class="teal">Quaid-e-Azam</span></h2>
                <div class="divider-teal"></div>
            </div>
            <div class="col-lg-4 text-lg-end" data-aos="fade-left">
                <a href="#gallery" class="btn btn-outline-secondary px-4 py-2 rounded-pill" style="font-size:.85rem;border-color:var(--gray-200);">
                    Full Gallery <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>

        <div class="gallery-grid">
            <?php
            $gallery_images = [
                ['src'=>'assets/images/image1.png','alt'=>'Students at Quaid-e-Azam campus'],
                ['src'=>'assets/images/image2.png','alt'=>'Academic activities at Quaid-e-Azam campus'],
                ['src'=>'assets/images/image3.png','alt'=>'Campus event at Quaid-e-Azam Group of Colleges'],
                ['src'=>'assets/images/campus-hero.png','alt'=>'Quaid-e-Azam campus building'],
                ['src'=>'assets/images/campus-hero1.jpg','alt'=>'Quaid-e-Azam campus exterior'],
                ['src'=>'assets/images/banner.jpg','alt'=>'Quaid-e-Azam college banner'],
                ['src'=>'assets/images/pic.jpg','alt'=>'Quaid-e-Azam college students'],
                ['src'=>'assets/images/clg add.jpg','alt'=>'Quaid-e-Azam college admission campaign'],
            ];
            foreach ($gallery_images as $i => $image): ?>
            <a class="gallery-item <?= $i >= 6 ? 'content-hidden' : '' ?>" href="<?= htmlspecialchars($image['src']) ?>" data-load-more-item="gallery" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
                <img src="<?= htmlspecialchars(str_replace(' ', '%20', $image['src'])) ?>" alt="<?= htmlspecialchars($image['alt']) ?>" loading="lazy" width="480" height="360">
            </a>
            <?php endforeach; ?>
        </div>
        <div class="load-more-wrap">
            <button type="button" class="program-tab-btn" data-load-more-button="gallery">Load More Gallery</button>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>

<!-- Search Overlay -->
<div class="search-overlay" id="searchOverlay">
    <div class="search-box">
        <input type="text" 
               id="searchInput"
               placeholder="Search programs, news, admissions..."
               oninput="liveSearch(this.value)">
        <button onclick="toggleSearch()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="search-results" id="searchResults"></div>
</div>

<script>
const searchData = [
    {title: 'FSc Pre-Medical', type: 'Program', link: '#programs'},
    {title: 'FSc Pre-Engineering', type: 'Program', link: '#programs'},
    {title: 'ICS Computer Science', type: 'Program', link: '#programs'},
    {title: 'I.Com Commerce', type: 'Program', link: '#programs'},
    {title: 'BSCS Computer Science', type: 'Program', link: '#programs'},
    {title: 'BS Information Technology', type: 'Program', link: '#programs'},
    {title: 'NAVTTC Web Development', type: 'Course', link: '#navttc-courses'},
    {title: 'NAVTTC Graphic Designing', type: 'Course', link: '#navttc-courses'},
    {title: 'NAVTTC Artificial Intelligence', type: 'Course', link: '#navttc-courses'},
    {title: 'Online Admissions 2026', type: 'Admissions', link: '#admissions'},
    {title: 'Rajanpur Campus', type: 'Campus', link: 'campus-rajanpur.php'},
    {title: 'Fazilpur Campus', type: 'Campus', link: 'campus-fazilpur.php'},
    {title: 'Kot Mithan Campus', type: 'Campus', link: 'campus-kotmithan.php'},
    {title: 'Fee Structure', type: 'Info', link: '#admissions'},
    {title: 'Student Portal Login', type: 'Portal', link: 'modules/auth/login.php'},
    {title: 'Contact Us', type: 'Info', link: 'contact.php'},
];

function toggleSearch(){
    const overlay = document.getElementById('searchOverlay');
    if (!overlay) return;
    overlay.classList.toggle('active');
    if(overlay.classList.contains('active')){
        const input = document.getElementById('searchInput');
        if (input) input.focus();
    }
}

function liveSearch(query){
    const results = document.getElementById('searchResults');
    if(query.length < 2){ results.innerHTML = ''; return; }
    
    const filtered = searchData.filter(item => 
        item.title.toLowerCase().includes(query.toLowerCase()) ||
        item.type.toLowerCase().includes(query.toLowerCase())
    );
    
    if(filtered.length === 0){
        results.innerHTML = '<div class="search-no-result">No results found</div>';
        return;
    }
    
    results.innerHTML = filtered.map(item => `
        <a href="${item.link}" class="search-result-item" onclick="toggleSearch()">
            <span class="search-result-type">${item.type}</span>
            <span class="search-result-title">${item.title}</span>
            <i class="fas fa-arrow-right"></i>
        </a>
    `).join('');
}

// Close search on Escape key
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') {
        const overlay = document.getElementById('searchOverlay');
        if(overlay && overlay.classList.contains('active')) toggleSearch();
    }
});

(function () {
    const nav = document.querySelector('.navbar-campus');
    if (nav) {
        window.addEventListener('scroll', function () {
            nav.classList.toggle('scrolled', window.scrollY > 20);
        }, { passive: true });
    }
})();
</script>

<script>
(function(){
  const slides = document.querySelectorAll('.qs-slide');
  const dots = document.querySelectorAll('.qs-dot');
  const tabs = document.querySelectorAll('.qs-tab');
  const progress = document.getElementById('qsProgress');
  let current = 0;
  let timer, progressTimer;

  function goTo(n) {
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    tabs[current].classList.remove('active');
    current = (n + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
    tabs[current].classList.add('active');
    resetProgress();
  }

  function resetProgress() {
    if(progress) {
      progress.style.transition = 'none';
      progress.style.width = '0%';
      setTimeout(() => {
        progress.style.transition = 'width 5s linear';
        progress.style.width = '100%';
      }, 30);
    }
  }

  function startAuto() {
    timer = setInterval(() => goTo(current + 1), 5000);
  }

  function stopAuto() { clearInterval(timer); }

  document.getElementById('qsNext').addEventListener('click', () => { stopAuto(); goTo(current+1); startAuto(); });
  document.getElementById('qsPrev').addEventListener('click', () => { stopAuto(); goTo(current-1); startAuto(); });

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      stopAuto();
      goTo(parseInt(tab.dataset.slide));
      startAuto();
    });
  });

  const wrap = document.querySelector('.qs-hero-wrap');
  if(wrap) {
    wrap.addEventListener('mouseenter', stopAuto);
    wrap.addEventListener('mouseleave', startAuto);
  }

  resetProgress();
  startAuto();
})();
</script>

<!-- AOS Animation -->
<script defer src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Init AOS
    if (window.AOS) {
        AOS.init({ once: true, offset: 60, duration: 700, easing: 'ease-out-cubic' });
    }

    // Navbar scroll effect
    const nav = document.getElementById('mainNav');
    window.addEventListener('scroll', () => {
        if (nav) nav.classList.toggle('scrolled', window.scrollY > 60);
    });

    // Program section tabs
    document.querySelectorAll('.program-tab-btn[data-program-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.programTab;
            document.querySelectorAll('.program-tab-btn[data-program-tab]').forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.program-tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            const panel = document.getElementById('program-panel-' + tab);
            if (panel) panel.classList.add('active');
        });
    });

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1 }
    );

    document.querySelectorAll('.lazy-section')
      .forEach(el => observer.observe(el));

    document.querySelectorAll('[data-campus-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const campus = btn.dataset.campusTab;
            document.querySelectorAll('[data-campus-tab]').forEach(tab => {
                tab.classList.remove('active');
                tab.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('[data-campus-panel]').forEach(panel => {
                panel.classList.toggle('active', panel.dataset.campusPanel === campus);
            });
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
        });
    });

    const facultyToggle = document.getElementById('facultyToggle');
    const facultyPanel = document.getElementById('facultyPanel');
    if (facultyToggle && facultyPanel) {
        facultyToggle.addEventListener('click', () => {
            const show = !facultyPanel.classList.contains('visible');
            facultyPanel.classList.toggle('visible', show);
            facultyToggle.setAttribute('aria-expanded', show ? 'true' : 'false');
            facultyToggle.textContent = show ? 'Hide Leadership' : 'Show Leadership';
        });
    }

    document.querySelectorAll('[data-load-more-button]').forEach(button => {
        button.addEventListener('click', () => {
            const group = button.dataset.loadMoreButton;
            document.querySelectorAll(`[data-load-more-item="${group}"].content-hidden`).forEach(item => {
                item.classList.remove('content-hidden');
            });
            button.style.display = 'none';
        });
    });
});
</script>

<!-- Removed Live Server websocket injection (was causing WebSocket errors when using PHP server) -->
<!-- Back to Top Button -->
<button id="backToTop" 
        onclick="window.scrollTo({top:0,behavior:'smooth'})" 
        style="
            position:fixed;
            bottom:30px;
            right:30px;
            width:45px;
            height:45px;
            background:var(--teal);
            color:var(--navy);
            border:none;
            border-radius:50%;
            font-size:1.1rem;
            cursor:pointer;
            box-shadow:0 4px 16px rgba(78,194,181,.4);
            z-index:999;
            display:none;
            transition:all .3s ease;
        ">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
// Show/hide back to top button & Page Loader
document.addEventListener('DOMContentLoaded', function() {
    const loader = document.getElementById('page-loader');
    if (loader) loader.style.display = 'none';
});

window.addEventListener('scroll', function(){
    const btn = document.getElementById('backToTop');
    if(btn) {
        if(window.scrollY > 400){
            btn.style.display = 'block';
        } else {
            btn.style.display = 'none';
        }
    }
});

// Active nav link on scroll
window.addEventListener('scroll', function(){
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link-main');
    
    let current = '';
    sections.forEach(section => {
        const sectionTop = section.offsetTop - 120;
        const sectionHeight = section.offsetHeight;
        if(window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight){
            current = section.getAttribute('id');
        }
    });

    navLinks.forEach(link => {
        link.classList.remove('active');
        if(link.getAttribute('href') === '#' + current){
            link.classList.add('active');
        }
        // Keep home active if at the very top
        if(window.scrollY < 200 && (link.getAttribute('href') === 'index.php' || link.getAttribute('href') === '#')) {
            link.classList.add('active');
        }
    });
});

// Smooth scroll for all anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e){
        const href = this.getAttribute('href');
        if(href !== '#') {
            e.preventDefault();
            const target = document.querySelector(href);
            if(target){
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// Close mobile navbar on link click
document.querySelectorAll('.nav-link-main').forEach(link => {
    link.addEventListener('click', function(){
        const navbar = document.getElementById('mobileNav');
        if(navbar && navbar.classList.contains('show')){
            const bsCollapse = bootstrap.Collapse.getInstance(navbar);
            if(bsCollapse) bsCollapse.hide();
            else navbar.classList.remove('show');
        }
    });
});
</script>
</body>
</html>

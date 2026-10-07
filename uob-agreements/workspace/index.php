<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Overview', 'dashboard');
?>

<style>

/* =========================================================
   UOB WORKSPACE — NEW DASHBOARD FROM SCRATCH
========================================================= */

.uob-dashboard{
    max-width:1450px;
    margin:0 auto;
    padding:10px 0 50px;
}


/* TOP INTRO */

.uob-dashboard-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:30px;

    padding:26px 30px;

    background:
        radial-gradient(circle at 88% 20%, rgba(76,165,214,.28), transparent 28%),
        radial-gradient(circle at 72% 110%, rgba(201,162,39,.20), transparent 32%),
        linear-gradient(120deg, #082b4d 0%, #0d4775 55%, #16739d 100%);

    border:1px solid #e5ebf1;
    border-radius:22px;

    box-shadow:0 8px 28px rgba(15,42,68,.05);
    overflow:hidden;
    position:relative;
}

.uob-dashboard-top::before{
    content:"";
    position:absolute;
    width:300px;
    height:300px;
    right:-90px;
    top:-140px;
    border:1px solid rgba(255,255,255,.15);
    border-radius:50%;
}

.uob-dashboard-top::after{
    content:"";
    position:absolute;
    width:180px;
    height:180px;
    right:120px;
    bottom:-110px;
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.08);
    border-radius:42px;
    transform:rotate(30deg);
}

.uob-dashboard-top-copy{
    min-width:0;
}

.uob-dashboard-top .eyebrow{
    margin:0 0 6px;

    color:#b18a2e;

    font-size:11px;
    font-weight:900;

    text-transform:uppercase;
    letter-spacing:.08em;
}

.uob-dashboard-top h1{
    margin:0;

    color:#092f53;

    font-size:30px;
    font-weight:950;

    line-height:1.15;
    letter-spacing:-.025em;
}

.uob-dashboard-top p{
    max-width:720px;

    margin:9px 0 0;

    color:#748395;

    font-size:12px;
    line-height:1.7;
}

.dashboard-role-chip{
    display:inline-flex;
    align-items:center;

    margin-top:14px;
    padding:7px 12px;

    color:#0c4c84;

    background:#edf5fb;
    border:1px solid #d6e5f1;

    border-radius:999px;

    font-size:9px;
    font-weight:850;
}

.uob-dashboard-mark{
    width:86px;
    height:86px;

    flex:0 0 86px;

    display:grid;
    place-items:center;

    border-radius:22px;

    background:#0b3b66;
}

.uob-dashboard-mark span{
    width:38px;
    height:38px;

    display:block;

    border:8px solid #fff;
    border-top-color:#d8b44b;

    border-radius:50%;
}


/* SECTION LABEL */

.uob-section-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;

    gap:16px;

    margin:30px 0 12px;
}

.uob-section-head h2{
    margin:0;

    color:#0b3157;

    font-size:18px;
    font-weight:950;
}

.uob-section-head p{
    margin:4px 0 0;

    color:#8391a0;

    font-size:10px;
}

.uob-section-head a{
    color:#1765a4;

    font-size:9px;
    font-weight:850;

    text-decoration:none;
}


/* KPI */

.dashboard-priority-grid{
    display:grid !important;

    grid-template-columns:repeat(4,minmax(0,1fr)) !important;

    gap:14px !important;
}

.dashboard-priority-card{
    position:relative !important;

    min-height:135px !important;

    display:flex !important;
    flex-direction:column !important;

    padding:18px 19px !important;

    background:#fff !important;

    border:1px solid #e4eaf0 !important;
    border-radius:16px !important;

    box-shadow:0 5px 16px rgba(15,42,68,.04) !important;

    text-decoration:none !important;

    transition:.18s ease !important;
}

.dashboard-priority-card:hover{
    transform:translateY(-3px) !important;

    box-shadow:0 12px 28px rgba(15,42,68,.09) !important;
}

.dashboard-priority-card::after{
    content:"";

    position:absolute;

    top:18px;
    right:18px;

    width:8px;
    height:8px;

    border-radius:50%;

    background:#8294a5;
}

.dashboard-priority-card.is-agreement::after{
    background:#0b5aa4;
}

.dashboard-priority-card.is-initiative::after{
    background:#b89a68;
}

.dashboard-priority-card.is-warning::after{
    background:#d39d27;
}

.dashboard-priority-card.is-danger::after{
    background:#d7515c;
}

.dashboard-priority-card > span{
    color:#718397 !important;

    font-size:9px !important;
    font-weight:850 !important;

    text-transform:uppercase !important;
    letter-spacing:.05em !important;
}

.dashboard-priority-card > strong{
    display:block !important;

    margin-top:10px !important;

    color:#0a3154 !important;

    font-size:32px !important;
    font-weight:950 !important;

    line-height:1 !important;
}

.dashboard-priority-card > small{
    display:block !important;

    margin-top:auto !important;
    padding-top:12px !important;

    color:#8b99a8 !important;

    font-size:8.7px !important;

    line-height:1.45 !important;
}


/* MAIN WORKSPACE */

.uob-main-grid{
    display:grid;

    grid-template-columns:minmax(0,1.6fr) minmax(310px,.7fr);

    gap:16px;
}

.uob-card{
    background:#fff;

    border:1px solid #e3e9ef;
    border-radius:18px;

    box-shadow:0 6px 20px rgba(15,42,68,.045);

    overflow:hidden;
}

.uob-card-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;

    gap:14px;

    padding:18px 20px;

    border-bottom:1px solid #edf1f5;
}

.uob-card-head h3{
    margin:0;

    color:#0d3154;

    font-size:14px;
    font-weight:950;
}

.uob-card-head p{
    margin:4px 0 0;

    color:#8895a3;

    font-size:9px;
}

.uob-card-head a{
    color:#1765a4;

    font-size:9px;
    font-weight:850;

    text-decoration:none;
}


/* WORK LIST */

.dashboard-list{
    margin:0 !important;
    padding:0 !important;

    list-style:none !important;
}

.dashboard-list-item{
    display:flex !important;
    align-items:center !important;
    justify-content:space-between !important;

    gap:14px !important;

    min-height:76px !important;

    padding:14px 18px !important;

    border-bottom:1px solid #edf1f5 !important;

    transition:.15s ease !important;
}

.dashboard-list-item:last-child{
    border-bottom:0 !important;
}

.dashboard-list-item:hover{
    background:#f8fafc !important;
}

.dashboard-list-item strong{
    display:block !important;

    color:#173650 !important;

    font-size:10px !important;
    font-weight:900 !important;
}

.dashboard-list-item small{
    display:block !important;

    margin-top:4px !important;

    color:#8c99a7 !important;

    font-size:8.5px !important;

    line-height:1.4 !important;
}


/* QUICK ACTIONS */

.dashboard-action-grid{
    display:grid !important;

    grid-template-columns:1fr !important;

    gap:0 !important;

    padding:0 !important;
}

.dashboard-action{
    min-height:auto !important;

    display:grid !important;
    grid-template-columns:1fr auto !important;
    grid-template-areas:
        "title arrow"
        "desc arrow";

    gap:2px 12px !important;

    padding:15px 17px !important;

    background:#fff !important;

    border:0 !important;
    border-bottom:1px solid #edf1f5 !important;

    border-radius:0 !important;

    text-decoration:none !important;

    transition:.15s ease !important;
}

.dashboard-action:last-child{
    border-bottom:0 !important;
}

.dashboard-action:hover{
    background:#f7fafc !important;
}

.dashboard-action strong{
    grid-area:title;

    color:#0e3355 !important;

    font-size:10px !important;
    font-weight:900 !important;
}

.dashboard-action small{
    grid-area:desc;

    margin:2px 0 0 !important;

    color:#8b98a6 !important;

    font-size:8px !important;

    line-height:1.4 !important;
}

.dashboard-action > span{
    grid-area:arrow;

    align-self:center;

    color:#1765a4 !important;

    font-size:9px !important;
    font-weight:900 !important;
}


/* INITIATIVE AREA */

.uob-initiative-box{
    min-height:80px;
}

.dashboard-empty{
    padding:22px !important;

    color:#8b99a7 !important;

    font-size:9px !important;

    text-align:center !important;
}


/* HIDDEN JS TARGET */

.uob-hidden{
    display:none !important;
}


/* RESPONSIVE (general) */

@media(max-width:1100px){

    .dashboard-priority-grid{
        grid-template-columns:
            repeat(2,minmax(0,1fr)) !important;
    }

    .uob-main-grid{
        grid-template-columns:1fr;
    }

}

@media(max-width:800px){

    .uob-dashboard-top{
        align-items:flex-start;
    }

    .uob-dashboard-mark{
        display:none;
    }

}

@media(max-width:560px){

    .dashboard-priority-grid{
        grid-template-columns:1fr !important;
    }

    .uob-dashboard-top{
        padding:22px;
    }

    .uob-dashboard-top h1{
        font-size:25px;
    }

}


/* HERO FINAL FIX */

.uob-dashboard-top h1{
    color:#ffffff !important;
}

.uob-dashboard-top p{
    color:rgba(255,255,255,.82) !important;
}

.uob-dashboard-mark{
    display:none !important;
}

.dashboard-role-chip{
    color:#ffffff !important;
    background:rgba(255,255,255,.11) !important;
    border:1px solid rgba(255,255,255,.18) !important;
}

.uob-dashboard-top .eyebrow{
    color:#f2c75d !important;
}

.uob-dashboard-top-copy{
    position:relative;
    z-index:2;
}


/* =========================================================
   PORTFOLIO OVERVIEW — FINAL (single source of truth)
   Every selector is prefixed with .dashboard-portfolio-grid
   so it wins over any older / external CSS (e.g. green).
========================================================= */

.dashboard-portfolio-grid{
    display:flex !important;
    flex-direction:column !important;
    gap:16px !important;
}


/* ---------- CARD ---------- */

.dashboard-portfolio-grid .dashboard-portfolio-card{
    --accent:#1F5FA8;
    --accent-dark:#1F5FA8;
    --left-bg:linear-gradient(135deg, #F4F8FD 0%, #E8F1FB 100%);

    position:relative !important;

    display:grid !important;
    grid-template-columns:clamp(240px,23%,290px) minmax(0,1fr) !important;
    align-items:stretch !important;

    min-height:120px !important;

    padding:0 !important;

    /* clips the accent bar to the rounded corners */
    overflow:hidden !important;
    isolation:isolate;

    background:#fff !important;

    /* uniform neutral border — the colored edge is the ::before bar */
    border:1px solid #DCE5ED !important;
    border-left:1px solid #DCE5ED !important;
    border-radius:20px !important;

    box-shadow:0 8px 24px rgba(15,42,68,.05) !important;
}

/* Initiatives = GOLD */
.dashboard-portfolio-grid .dashboard-portfolio-card.is-initiative{
    --accent:#B8964E;
    --accent-dark:#8A6A2B;
    --left-bg:linear-gradient(135deg, #FBF6EA 0%, #F3E7C9 100%);
}

/* Colored edge: part of the card, follows the curved corners */
.dashboard-portfolio-grid .dashboard-portfolio-card::before{
    content:"";

    position:absolute;
    top:0;
    bottom:0;
    left:0;

    width:6px;

    background:var(--accent);

    z-index:3;
    pointer-events:none;
}


/* ---------- LEFT COLUMN (stops exactly at the divider) ---------- */

.dashboard-portfolio-grid .dashboard-portfolio-card > header{
    position:relative !important;

    display:flex !important;
    flex-direction:column !important;
    align-items:flex-start !important;
    justify-content:center !important;

    box-sizing:border-box !important;
    width:100% !important;
    height:100% !important;
    min-width:0 !important;

    margin:0 !important;
    padding:24px 22px 24px 32px !important;

    /* gradient lives only inside this column */
    background:var(--left-bg) !important;
    background-image:var(--left-bg) !important;

    border:0 !important;
    border-right:1px solid #DFE6ED !important;
    border-radius:0 !important;
    box-shadow:none !important;
}

/* kill any old decorative layers (green glow etc.) */
.dashboard-portfolio-grid .dashboard-portfolio-card > header::before,
.dashboard-portfolio-grid .dashboard-portfolio-card > header::after{
    content:none !important;
    display:none !important;
}

/* inner wrapper: stack tightly, no stretched gaps */
.dashboard-portfolio-grid .dashboard-portfolio-card > header > div{
    display:block !important;
    height:auto !important;
    min-height:0 !important;
    width:100% !important;
    margin:0 !important;
    padding:0 !important;
}

/* old "Open" link inside header stays hidden */
.dashboard-portfolio-grid .dashboard-portfolio-card > header > a{
    display:none !important;
}


/* ---------- AGREEMENTS / INITIATIVES = plain text, no pill ---------- */

.dashboard-portfolio-grid .dashboard-portfolio-card header .dashboard-module-label{
    display:block !important;

    width:auto !important;
    height:auto !important;
    min-height:0 !important;

    margin:0 0 8px !important;
    padding:0 !important;

    color:var(--accent-dark) !important;

    background:none !important;
    background-color:transparent !important;
    background-image:none !important;

    border:0 !important;
    border-radius:0 !important;
    outline:0 !important;
    box-shadow:none !important;
    backdrop-filter:none !important;

    font-size:12px !important;
    font-weight:700 !important;
    line-height:1.2 !important;

    letter-spacing:.08em !important;
    text-transform:uppercase !important;
}

.dashboard-portfolio-grid .dashboard-portfolio-card header .dashboard-module-label::before,
.dashboard-portfolio-grid .dashboard-portfolio-card header .dashboard-module-label::after{
    content:none !important;
    display:none !important;
}


/* ---------- TITLE + DESCRIPTION ---------- */

.dashboard-portfolio-grid .dashboard-portfolio-card h3{
    margin:0 !important;

    color:#082F54 !important;

    font-size:21px !important;
    font-weight:950 !important;

    line-height:1.15 !important;
}

.dashboard-portfolio-grid .dashboard-portfolio-card header p{
    max-width:280px !important;

    margin:8px 0 0 !important;

    color:#738497 !important;

    font-size:11px !important;
    line-height:1.6 !important;
}


/* ---------- RIGHT METRICS COLUMN (clean white) ---------- */

.dashboard-portfolio-grid .dashboard-module-metrics{
    width:100% !important;
    height:100% !important;
    min-width:0 !important;

    display:grid !important;

    grid-template-columns:repeat(4,minmax(0,1fr)) !important;

    align-items:stretch !important;

    gap:0 !important;

    background:#fff !important;

    border:0 !important;
}

.dashboard-portfolio-grid .dashboard-module-metric{
    display:flex !important;
    flex-direction:column !important;
    justify-content:center !important;
    align-items:flex-start !important;

    min-width:0 !important;
    min-height:100% !important;

    padding:18px 22px !important;

    background:#fff !important;

    border:0 !important;
    border-right:1px solid #E7ECF1 !important;
    border-radius:0 !important;

    box-shadow:none !important;
}

.dashboard-portfolio-grid .dashboard-module-metric:last-child{
    border-right:0 !important;
}

.dashboard-portfolio-grid .dashboard-module-metric strong,
.dashboard-portfolio-grid .dashboard-module-metric span,
.dashboard-portfolio-grid .dashboard-module-metric small{
    display:block !important;
}

/* remove old highlighted / green success states */
.dashboard-portfolio-grid .dashboard-module-metric.is-success{
    background:#fff !important;
    border-color:#E7ECF1 !important;
}

.dashboard-portfolio-grid .dashboard-module-metric.is-success strong,
.dashboard-portfolio-grid .dashboard-module-metric.is-success span,
.dashboard-portfolio-grid .dashboard-module-metric.is-success small{
    color:inherit !important;
}


/* ---------- PORTFOLIO RESPONSIVE ---------- */

@media(max-width:1000px){

    .dashboard-portfolio-grid .dashboard-portfolio-card{
        grid-template-columns:1fr !important;
    }

    .dashboard-portfolio-grid .dashboard-portfolio-card > header{
        min-height:0 !important;

        border-right:0 !important;
        border-bottom:1px solid #DFE6ED !important;
    }

    .dashboard-portfolio-grid .dashboard-module-metrics{
        grid-template-columns:repeat(2,minmax(0,1fr)) !important;
    }

    .dashboard-portfolio-grid .dashboard-module-metric{
        min-height:110px !important;
    }

    .dashboard-portfolio-grid .dashboard-module-metric:nth-child(2n){
        border-right:0 !important;
    }

    .dashboard-portfolio-grid .dashboard-module-metric:nth-child(-n+2){
        border-bottom:1px solid #E7ECF1 !important;
    }
}

@media(max-width:600px){

    .dashboard-portfolio-grid .dashboard-portfolio-card > header{
        padding:28px 24px 28px 34px !important;
    }

    .dashboard-portfolio-grid .dashboard-module-metrics{
        grid-template-columns:1fr !important;
    }

    .dashboard-portfolio-grid .dashboard-module-metric{
        border-right:0 !important;
        border-bottom:1px solid #E7ECF1 !important;
    }

    .dashboard-portfolio-grid .dashboard-module-metric:last-child{
        border-bottom:0 !important;
    }
}
/* =========================================================
   INTERACTIVE PORTFOLIO ACTIVITY HUB
========================================================= */

.activity-hub{
    position:relative;
    overflow:hidden;

    display:grid;
    grid-template-columns:minmax(480px,1.15fr) minmax(300px,.85fr);
    gap:0;

    min-height:430px;

    background:#fff;
    border:1px solid #dde6ee;
    border-radius:24px;

    box-shadow:0 12px 34px rgba(15,42,68,.055);
}


/* LEFT VISUAL AREA */

.activity-hub-visual{
    position:relative;
    min-height:430px;

    display:flex;
    align-items:center;
    justify-content:center;

    padding:36px;

    background:
        radial-gradient(
            circle at 50% 50%,
            rgba(31,95,168,.045),
            transparent 38%
        ),
        #fbfcfd;

    border-right:1px solid #e7edf2;
}


.activity-hub-orbit{
    position:relative;

    width:390px;
    height:330px;
}


/* decorative orbit lines */

.activity-hub-orbit::before{
    content:"";

    position:absolute;

    left:50%;
    top:50%;

    width:245px;
    height:245px;

    transform:translate(-50%,-50%);

    border:1px dashed #d8e1e9;
    border-radius:50%;
}


.activity-hub-orbit::after{
    content:"";

    position:absolute;

    left:50%;
    top:50%;

    width:310px;
    height:190px;

    transform:translate(-50%,-50%) rotate(-12deg);

    border:1px solid rgba(31,95,168,.08);
    border-radius:50%;
}


/* CENTER */

.activity-hub-center{
    position:absolute;

    left:50%;
    top:50%;

    width:142px;
    height:142px;

    transform:translate(-50%,-50%);

    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;

    text-align:center;

    background:#0b3559;

    border:8px solid #f5f8fb;
    border-radius:50%;

    box-shadow:
        0 0 0 1px #dce6ee,
        0 18px 35px rgba(12,49,84,.16);

    z-index:5;
}


.activity-hub-center::after{
    content:"";

    position:absolute;

    inset:-13px;

    border:1px solid rgba(31,95,168,.22);
    border-radius:50%;

    animation:hubPulse 2.6s ease-in-out infinite;
}


.activity-hub-center strong{
    color:#fff;

    font-size:26px;
    font-weight:950;

    line-height:1;
}


.activity-hub-center span{
    max-width:90px;

    margin-top:8px;

    color:rgba(255,255,255,.68);

    font-size:9px;
    font-weight:800;

    line-height:1.3;
}


/* NODES */

.activity-node{
    --node-color:#7c8fa3;

    position:absolute;

    width:118px;
    min-height:90px;

    display:flex;
    flex-direction:column;
    align-items:flex-start;
    justify-content:center;

    padding:14px 16px;

    background:#fff;

    border:1px solid #dfe7ee;
    border-radius:18px;

    box-shadow:0 8px 22px rgba(15,42,68,.055);

    cursor:pointer;

    transition:
        transform .22s ease,
        box-shadow .22s ease,
        border-color .22s ease;

    z-index:4;
}


.activity-node::before{
    content:"";

    width:9px;
    height:9px;

    margin-bottom:8px;

    background:var(--node-color);

    border-radius:50%;
}


.activity-node strong{
    color:#0b3157;

    font-size:22px;
    font-weight:950;

    line-height:1;
}


.activity-node span{
    margin-top:5px;

    color:#60758a;

    font-size:9px;
    font-weight:850;

    line-height:1.35;
}


.activity-node:hover,
.activity-node.is-active{
    transform:translateY(-5px) scale(1.03);

    border-color:var(--node-color);

    box-shadow:0 14px 30px rgba(15,42,68,.11);
}


.activity-node.is-active{
    box-shadow:
        0 14px 30px rgba(15,42,68,.10),
        0 0 0 3px color-mix(in srgb, var(--node-color) 12%, transparent);
}


/* positions */

.activity-node[data-hub-node="agreements"]{
    --node-color:#1F5FA8;

    left:0;
    top:25px;
}

.activity-node[data-hub-node="initiatives"]{
    --node-color:#B8964E;

    right:0;
    top:25px;
}

.activity-node[data-hub-node="review"]{
    --node-color:#73859A;

    left:15px;
    bottom:20px;
}

.activity-node[data-hub-node="updates"]{
    --node-color:#D39B2A;

    right:15px;
    bottom:20px;
}


/* CONNECTOR LINES */

.activity-connector{
    position:absolute;

    left:50%;
    top:50%;

    height:1px;

    background:#dbe4eb;

    transform-origin:left center;

    z-index:1;
}

.activity-connector.one{
    width:145px;
    transform:rotate(-150deg);
}

.activity-connector.two{
    width:145px;
    transform:rotate(-30deg);
}

.activity-connector.three{
    width:145px;
    transform:rotate(150deg);
}

.activity-connector.four{
    width:145px;
    transform:rotate(30deg);
}


/* RIGHT DETAIL PANEL */

.activity-detail{
    position:relative;

    padding:38px 36px;

    display:flex;
    flex-direction:column;
    justify-content:center;

    background:#fff;
}


.activity-detail-eyebrow{
    margin:0 0 10px;

    color:#8a99a7;

    font-size:9px;
    font-weight:900;

    letter-spacing:.09em;
    text-transform:uppercase;
}


.activity-detail h3{
    margin:0;

    color:#082f54;

    font-size:26px;
    font-weight:950;

    line-height:1.15;
}


.activity-detail-value{
    display:flex;
    align-items:flex-end;

    gap:10px;

    margin-top:24px;
}


.activity-detail-value strong{
    color:#0b3157;

    font-size:58px;
    font-weight:950;

    line-height:.9;
}


.activity-detail-value span{
    padding-bottom:6px;

    color:#8b98a5;

    font-size:10px;
    font-weight:800;
}


.activity-detail p{
    max-width:390px;

    margin:20px 0 0;

    color:#718397;

    font-size:11px;

    line-height:1.7;
}


/* percentage */

.activity-share{
    margin-top:26px;
}


.activity-share-head{
    display:flex;
    align-items:center;
    justify-content:space-between;

    margin-bottom:8px;

    color:#60758a;

    font-size:9px;
    font-weight:850;
}


.activity-share-track{
    height:7px;

    overflow:hidden;

    background:#edf1f4;

    border-radius:999px;
}


.activity-share-fill{
    width:0;
    height:100%;

    background:#1F5FA8;

    border-radius:999px;

    transition:
        width .45s ease,
        background .25s ease;
}


.activity-detail-link{
    display:inline-flex;
    align-items:center;

    align-self:flex-start;

    margin-top:28px;

    color:#1765a4;

    font-size:10px;
    font-weight:900;

    text-decoration:none;
}


.activity-detail-link:hover{
    text-decoration:underline;
}


/* ANIMATIONS */

@keyframes hubPulse{
    0%,100%{
        transform:scale(1);
        opacity:.7;
    }

    50%{
        transform:scale(1.07);
        opacity:.15;
    }
}


.activity-hub.is-ready .activity-node{
    animation:hubNodeIn .55s both;
}


.activity-hub.is-ready
.activity-node:nth-of-type(1){
    animation-delay:.05s;
}

.activity-hub.is-ready
.activity-node:nth-of-type(2){
    animation-delay:.13s;
}

.activity-hub.is-ready
.activity-node:nth-of-type(3){
    animation-delay:.21s;
}

.activity-hub.is-ready
.activity-node:nth-of-type(4){
    animation-delay:.29s;
}


@keyframes hubNodeIn{
    from{
        opacity:0;
        transform:scale(.88) translateY(12px);
    }

    to{
        opacity:1;
        transform:scale(1) translateY(0);
    }
}


/* RESPONSIVE */

@media(max-width:1000px){

    .activity-hub{
        grid-template-columns:1fr;
    }

    .activity-hub-visual{
        border-right:0;
        border-bottom:1px solid #e7edf2;
    }

}


@media(max-width:600px){

    .activity-hub-visual{
        padding:20px 10px;
        min-height:400px;
    }

    .activity-hub-orbit{
        transform:scale(.82);
    }

    .activity-detail{
        padding:28px 24px;
    }

}
</style>


<div
    class="alert alert-danger d-none"
    role="alert"
    tabindex="-1"
    data-dashboard-alert
></div>


<div
    class="loading-state"
    data-dashboard-loading
    aria-live="polite"
>
    <div
        class="spinner-border text-primary"
        aria-hidden="true"
    ></div>

    <span>Preparing your workspace…</span>
</div>


<div
    class="d-none uob-dashboard"
    data-dashboard-content
>


    <!-- TOP -->

    <section class="uob-dashboard-top">

        <div class="uob-dashboard-top-copy">

            <p
                class="eyebrow"
                data-dashboard-greeting
            >
                Welcome back
            </p>

            <h1 data-dashboard-title>
                Your partnerships workspace
            </h1>

            <p data-dashboard-description>
                Manage agreements, initiatives,
                reviews and updates from one place.
            </p>

            <span
                class="dashboard-role-chip"
                data-dashboard-role
            ></span>

        </div>


        <div
            class="uob-dashboard-mark"
            aria-hidden="true"
        >
            <span></span>
        </div>

    </section>


    <!-- PRIORITIES -->

    <div class="uob-section-head">

        <div>

            <h2>Priority overview</h2>

            <p>
                The items that need your attention now.
            </p>

        </div>

    </div>


    <section
        class="dashboard-priority-grid"
        data-dashboard-priorities
        aria-label="Priority briefing"
    ></section>
<!-- INTERACTIVE PORTFOLIO ACTIVITY -->

<div class="uob-section-head">
    <div>
        <h2>Portfolio activity</h2>

        <p>
            Explore your current Agreements, Initiatives,
            reviews and updates.
        </p>
    </div>
</div>


<section
    class="activity-hub"
    id="portfolioActivityHub"
>

    <!-- VISUAL -->

    <div class="activity-hub-visual">

        <div class="activity-hub-orbit">

            <span class="activity-connector one"></span>
            <span class="activity-connector two"></span>
            <span class="activity-connector three"></span>
            <span class="activity-connector four"></span>


            <div class="activity-hub-center">

                <strong id="activityHubTotal">
                    0
                </strong>

                <span>
                    current items
                </span>

            </div>


            <button
                type="button"
                class="activity-node is-active"
                data-hub-node="agreements"
            >
                <strong>0</strong>
                <span>Agreements</span>
            </button>


            <button
                type="button"
                class="activity-node"
                data-hub-node="initiatives"
            >
                <strong>0</strong>
                <span>Initiatives</span>
            </button>


            <button
                type="button"
                class="activity-node"
                data-hub-node="review"
            >
                <strong>0</strong>
                <span>In review</span>
            </button>


            <button
                type="button"
                class="activity-node"
                data-hub-node="updates"
            >
                <strong>0</strong>
                <span>Reports & updates</span>
            </button>

        </div>

    </div>


    <!-- DETAILS -->

    <aside class="activity-detail">

        <p class="activity-detail-eyebrow">
            Selected activity
        </p>

        <h3 id="activityDetailTitle">
            Agreements
        </h3>


        <div class="activity-detail-value">

            <strong id="activityDetailValue">
                0
            </strong>

            <span>
                items
            </span>

        </div>


        <p id="activityDetailDescription">
            Drafts, returns, or Agreement reviews
            currently requiring your attention.
        </p>


        <div class="activity-share">

            <div class="activity-share-head">

                <span>
                    Share of current activity
                </span>

                <strong id="activityShareValue">
                    0%
                </strong>

            </div>


            <div class="activity-share-track">

                <div
                    class="activity-share-fill"
                    id="activityShareFill"
                ></div>

            </div>

        </div>


        <a
            href="agreements.php"
            class="activity-detail-link"
            id="activityDetailLink"
        >
            Open Agreements →
        </a>

    </aside>

</section>

    <!-- MAIN WORKSPACE -->

    <div class="uob-section-head">

        <div>

            <h2>Current workspace</h2>

            <p>
                Continue your work
                or open a common action.
            </p>

        </div>

    </div>


    <section class="uob-main-grid">


        <article class="uob-card">

            <div class="uob-card-head">

                <div>

                    <h3 data-primary-work-title>
                        Agreement activity
                    </h3>

                    <p data-primary-work-description>
                        Drafts, reviews and active records.
                    </p>

                </div>


                <a
                    href="#"
                    data-primary-work-link
                >
                    View all
                </a>

            </div>


            <ul
                class="dashboard-list"
                data-primary-work-list
            ></ul>

        </article>


        <article class="uob-card">

            <div class="uob-card-head">

                <div>

                    <h3>Quick actions</h3>

                    <p>
                        Shortcuts based
                        on your permissions.
                    </p>

                </div>

            </div>


            <div
                class="dashboard-action-grid"
                data-dashboard-actions
                aria-label="Available actions"
            ></div>

        </article>

    </section>


    <!-- PORTFOLIO -->

    <div class="uob-section-head">

        <div>

            <h2>Portfolio overview</h2>

            <p>
                Agreements and Initiatives
                shown as separate work areas.
            </p>

        </div>

    </div>


    <section
        class="dashboard-portfolio-grid"
        aria-label="Agreements and Initiatives overview"
    >


        <article
            class="dashboard-portfolio-card is-agreement"
            data-agreement-portfolio
        >

            <header>

                <div>

                    <span class="dashboard-module-label">
                        Agreements
                    </span>

                    <h3>
                        Partnership portfolio
                    </h3>

                    <p>
                        Creation, review,
                        activation and reporting.
                    </p>

                </div>


                <a href="agreements.php">
                    Open
                </a>

            </header>


            <div
                class="dashboard-module-metrics"
                data-agreement-metrics
                aria-live="polite"
            ></div>

        </article>


        <article
            class="dashboard-portfolio-card is-initiative"
            data-initiative-portfolio
        >

            <header>

                <div>

                    <span class="dashboard-module-label">
                        Initiatives
                    </span>

                    <h3>
                        Impact portfolio
                    </h3>

                    <p>
                        Requests, approvals
                        and Initiative records.
                    </p>

                </div>


                <a href="initiative-workflow.php">
                    Open
                </a>

            </header>


            <div
                class="dashboard-module-metrics"
                data-initiative-metrics
                aria-live="polite"
            >

                <div class="dashboard-module-loading">
                    Loading Initiative work…
                </div>

            </div>

        </article>

    </section>


    <!-- INITIATIVE WORK -->

    <div class="uob-section-head">

        <div>

            <h2>Recent Initiative work</h2>

            <p>
                Initiative requests
                you create, review or follow.
            </p>

        </div>


        <a href="initiative-workflow.php">
            View all
        </a>

    </div>


    <section
        class="uob-card uob-initiative-box"
    >

        <ul
            class="dashboard-list"
            data-initiative-work-list
        >

            <li class="dashboard-empty">
                Loading Initiative work…
            </li>

        </ul>

    </section>


    <!-- hidden because dashboard.js still expects it -->

    <div class="uob-hidden">
        <div data-role-guidance></div>
    </div>


</div>

<script>
(function(){

    'use strict';


    const priorities =
        document.querySelector(
            '[data-dashboard-priorities]'
        );


    const hub =
        document.getElementById(
            'portfolioActivityHub'
        );


    if(!priorities || !hub){
        return;
    }


    const totalNode =
        document.getElementById(
            'activityHubTotal'
        );


    const detailTitle =
        document.getElementById(
            'activityDetailTitle'
        );


    const detailValue =
        document.getElementById(
            'activityDetailValue'
        );


    const detailDescription =
        document.getElementById(
            'activityDetailDescription'
        );


    const detailLink =
        document.getElementById(
            'activityDetailLink'
        );


    const shareValue =
        document.getElementById(
            'activityShareValue'
        );


    const shareFill =
        document.getElementById(
            'activityShareFill'
        );


    const nodes =
        Array.from(
            hub.querySelectorAll(
                '[data-hub-node]'
            )
        );


    const config = {

        agreements:{
            index:0,
            title:'Agreements',

            description:
                'Drafts, returns, or Agreement reviews currently requiring your attention.',

            href:'agreements.php',

            link:'Open Agreements →',

            color:'#1F5FA8'
        },


        initiatives:{
            index:1,
            title:'Initiatives',

            description:
                'Initiative requests, returns, and approvals ready for your next step.',

            href:'initiative-workflow.php',

            link:'Open Initiatives →',

            color:'#B8964E'
        },


        review:{
            index:2,
            title:'In review',

            description:
                'Agreement and Initiative records currently moving through approval routes.',

            href:'workflow-inbox.php',

            link:'Open reviews →',

            color:'#73859A'
        },


        updates:{
            index:3,
            title:'Reports & updates',

            description:
                'Reporting deadlines and Initiative updates that may require attention.',

            href:'performance-reports.php',

            link:'Open reports →',

            color:'#D39B2A'
        }

    };


    let values = [0,0,0,0];

    let activeKey = 'agreements';


    function getCardValue(card){

        if(!card){
            return 0;
        }


        const number =
            card.querySelector(
                'strong'
            );


        if(!number){
            return 0;
        }


        const value =
            Number(
                String(
                    number.textContent
                ).replace(
                    /[^\d.-]/g,
                    ''
                )
            );


        return Number.isFinite(value)
            ? value
            : 0;
    }


    function animateNumber(
        element,
        end
    ){

        const start = 0;

        const duration = 420;

        const startTime =
            performance.now();


        function frame(now){

            const progress =
                Math.min(
                    (now - startTime)
                    / duration,
                    1
                );


            const value =
                Math.round(
                    start +
                    (
                        end - start
                    ) * progress
                );


            element.textContent =
                String(value);


            if(progress < 1){

                requestAnimationFrame(
                    frame
                );

            }

        }


        requestAnimationFrame(
            frame
        );
    }


    function selectNode(key){

        const item =
            config[key];


        if(!item){
            return;
        }


        activeKey = key;


        nodes.forEach(
            function(node){

                node.classList.toggle(
                    'is-active',
                    node.dataset.hubNode
                        === key
                );

            }
        );


        const value =
            values[item.index] || 0;


        const total =
            values.reduce(
                function(sum,current){

                    return sum + current;

                },
                0
            );


        const percentage =
            total > 0
                ? Math.round(
                    (value / total) * 100
                )
                : 0;


        detailTitle.textContent =
            item.title;


        animateNumber(
            detailValue,
            value
        );


        detailDescription.textContent =
            item.description;


        detailLink.href =
            item.href;


        detailLink.textContent =
            item.link;


        shareValue.textContent =
            `${percentage}%`;


        shareFill.style.width =
            `${percentage}%`;


        shareFill.style.background =
            item.color;

    }


    function updateHub(){

        const cards =
            Array.from(
                priorities.children
            );


        if(cards.length < 4){
            return;
        }


        values =
            cards
                .slice(0,4)
                .map(
                    getCardValue
                );


        const total =
            values.reduce(
                function(sum,current){

                    return sum + current;

                },
                0
            );


        animateNumber(
            totalNode,
            total
        );


        nodes.forEach(
            function(node){

                const key =
                    node.dataset.hubNode;


                const item =
                    config[key];


                const number =
                    node.querySelector(
                        'strong'
                    );


                if(
                    item &&
                    number
                ){

                    animateNumber(
                        number,
                        values[item.index]
                            || 0
                    );

                }

            }
        );


        selectNode(
            activeKey
        );


        hub.classList.add(
            'is-ready'
        );

    }


    nodes.forEach(
        function(node){

           node.addEventListener(
    'mouseenter',
                function(){

                    selectNode(
                        node.dataset.hubNode
                    );

                }
            );

        }
    );


    const observer =
        new MutationObserver(
            updateHub
        );


    observer.observe(
        priorities,
        {
            childList:true,
            subtree:true,
            characterData:true
        }
    );


    updateHub();

})();
</script>
<?php

workspaceFooter([
    'assets/js/dashboard.js?v=20260803-unified-overview',
    'assets/js/dashboard-initiative-integration.js?v=20260803-unified-overview',
]);

?>
<?php
/**
 * Inline stylesheet shared by the standalone customer pages (approval, details, tracking).
 * No external assets on those pages — the URL carries a secret.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;
?>
<style>
	/* Motion: quiet and interaction-only — hover, press, focus, and states the visitor caused. */
	@keyframes fade-up { from { opacity: 0; transform: translateY(4px); } }
	body { margin: 0; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1a1a1a; background: #faf8f4; }
	.wrap { max-width: 760px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
	.brand { font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #9a7020; margin-bottom: 1.5rem; }
	.card { background: #fff; border: 1px solid #e8e2d6; border-radius: 8px; padding: 1.5rem; }
	h1 { font: 400 1.75rem/1.2 Georgia, "Times New Roman", serif; margin: 0 0 .75rem; }
	.meta { color: #555; font-size: .9375rem; margin-bottom: 1rem; }
	.preview { margin: 1.25rem 0; text-align: center; }
	.preview img { max-width: 100%; height: auto; border: 1px solid #e8e2d6; border-radius: 6px; transition: transform .25s ease, box-shadow .25s; }
	.preview a:hover img, .preview a:focus-visible img, .rev a:hover img, .rev a:focus-visible img { transform: scale(1.02); box-shadow: 0 6px 18px rgba(26, 26, 26, .08); }
	.actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.25rem; }
	button, .btn { font: 600 .8125rem/1 inherit; letter-spacing: .06em; text-transform: uppercase; padding: .9rem 1.4rem; border-radius: 4px; border: 1px solid #b8892b; background: #b8892b; color: #fff; cursor: pointer; text-decoration: none; transition: background .15s, color .15s, border-color .15s, transform .15s cubic-bezier(.2, .7, .2, 1), box-shadow .15s; }
	button:hover, .btn:hover { background: #9a7020; border-color: #9a7020; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(184, 137, 43, .22); }
	button:active, .btn:active { transform: translateY(0); box-shadow: none; transition-duration: .08s; }
	button:focus-visible, .btn:focus-visible { outline: none; box-shadow: 0 0 0 3px #f3ead6, 0 0 0 1px #b8892b; }
	button.secondary { background: #fff; color: #9a7020; }
	button.secondary:hover { background: #f3ead6; border-color: #b8892b; color: #9a7020; }
	textarea { width: 100%; box-sizing: border-box; min-height: 6rem; padding: .6rem; border: 1px solid #e8e2d6; border-radius: 4px; font: inherit; transition: border-color .15s, box-shadow .15s; }
	textarea:focus, input[type="text"]:focus, input[type="tel"]:focus { outline: none; border-color: #b8892b; box-shadow: 0 0 0 3px #f3ead6; }
	.error { color: #a3222b; margin: .5rem 0; }
	.ok { color: #2f7a3b; }
	.muted { color: #555; font-size: .875rem; }
	details { margin-top: 1rem; }
	details > summary { cursor: pointer; color: #555; transition: color .15s; }
	details > summary:hover { color: #9a7020; }
	details > summary:focus-visible { outline: 2px solid #b8892b; outline-offset: 3px; border-radius: 2px; }
	details[open] > :not(summary) { animation: fade-up .25s ease-out; }
	.grid { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; }
	.grid .full { grid-column: 1 / -1; }
	label.f { display: block; font-size: .75rem; color: #555; margin-bottom: .3rem; }
	input[type="text"], input[type="tel"] { width: 100%; box-sizing: border-box; padding: .7rem .8rem; border: 1px solid #e8e2d6; border-radius: 4px; font: inherit; transition: border-color .15s, box-shadow .15s; }
	.seg { display: flex; gap: .5rem; margin-bottom: 1rem; }
	.seg label { flex: 1; text-align: center; padding: .7rem; border: 1px solid #e8e2d6; border-radius: 4px; cursor: pointer; font-size: .875rem; transition: border-color .15s, background .15s, color .15s; }
	.seg label:hover { border-color: #d9c48f; }
	.seg label:has(input:focus-visible) { box-shadow: 0 0 0 3px #f3ead6; }
	.grid .only-company:not([hidden]), .grid .only-person:not([hidden]) { animation: fade-up .25s ease-out; }
	.seg input { display: none; }
	.seg input:checked + span { font-weight: 600; color: #9a7020; }
	.seg label:has(input:checked) { border-color: #b8892b; background: #f3ead6; }
	.grid [hidden] { display: none; }
	form:has(input[name="d_customer_type"][value="person"]:checked) .only-company, form:has(input[name="d_customer_type"][value="company"]:checked) .only-person { display: none; }
	.bank-details { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1.25rem; margin: 1rem 0; padding: 1rem 1.25rem; background: #faf8f4; border: 1px solid #e8e2d6; border-radius: 6px; font-size: .9375rem; }
	.bank-details dt { color: #555; }
	.bank-details dd { margin: 0; font-weight: 500; }
	.amount { font: 400 1.5rem/1.2 Georgia, serif; color: #9a7020; margin: .25rem 0 1rem; }
	.notice { padding: .75rem 1rem; border-radius: 6px; font-size: .875rem; margin-bottom: 1rem; animation: fade-up .25s ease-out; }
	.notice.ok { background: #eef7ef; border: 1px solid #cfe6d2; color: #2f7a3b; }
	.notice.err { background: #fbeef0; border: 1px solid #efc4c8; color: #a3222b; }
	.card a { color: #9a7020; transition: color .15s; }
	.card a:hover { color: #b8892b; }
	.card a:focus-visible { outline: 2px solid #b8892b; outline-offset: 3px; border-radius: 2px; }
	.track { margin-top: 1.25rem; font-size: .9375rem; }
	.track a { color: #9a7020; }
	.head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: .5rem; }
	.badge { display: inline-block; padding: .3rem .7rem; border-radius: 999px; background: #f3ead6; color: #9a7020; font-size: .8125rem; font-weight: 600; }
	.badge.done { background: #eef7ef; color: #2f7a3b; }
	.badge.off { background: #f1f1f1; color: #666; }
	.steps { list-style: none; margin: 1.5rem 0; padding: 0; display: grid; grid-template-columns: repeat(6, 1fr); gap: .5rem; counter-reset: s; }
	.steps li { position: relative; text-align: center; font-size: .75rem; line-height: 1.3; color: #888; padding-top: 2.2rem; transition: color .15s; }
	.steps li::before { counter-increment: s; content: counter(s); position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 1.75rem; height: 1.75rem; line-height: 1.75rem; border-radius: 50%; border: 2px solid #e8e2d6; background: #fff; font-weight: 600; }
	.steps li::after { content: ""; position: absolute; top: .875rem; left: calc(50% + 1rem); right: calc(-50% + 1rem); height: 2px; background: #e8e2d6; }
	.steps li:last-child::after { display: none; }
	.steps li.done { color: #1a1a1a; }
	.steps li.done::before { background: #b8892b; border-color: #b8892b; color: #fff; content: "\2713"; }
	.steps li.done::after { background: #b8892b; }
	.steps li.now { color: #1a1a1a; font-weight: 600; }
	.steps li.now::before { border-color: #b8892b; color: #9a7020; }
	.next { padding: 1rem 1.25rem; background: #f3ead6; border-radius: 6px; margin: 1.25rem 0; }
	.next h2 { margin: 0 0 .35rem; font: 600 1rem/1.3 inherit; }
	.next p { margin: .25rem 0; }
	h3 { font: 600 .8125rem/1 inherit; letter-spacing: .08em; text-transform: uppercase; color: #9a7020; margin: 1.75rem 0 .75rem; }
	.rev { display: grid; grid-template-columns: 96px 1fr; gap: 1rem; padding: .9rem 0; border-top: 1px solid #e8e2d6; align-items: start; }
	.rev img { width: 96px; height: 72px; object-fit: cover; border: 1px solid #e8e2d6; border-radius: 4px; transition: transform .25s ease, box-shadow .25s; }
	.rev .thumb { width: 96px; height: 72px; display: grid; place-items: center; background: #faf8f4; border: 1px solid #e8e2d6; border-radius: 4px; color: #9a7020; font-weight: 600; font-size: .75rem; }
	.rev p { margin: .15rem 0; }
	.kv { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1.25rem; margin: .5rem 0; font-size: .9375rem; }
	.kv dt { color: #555; }
	.kv dd { margin: 0; }
	@media (prefers-reduced-motion: reduce) { *, *::before, *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; animation-delay: 0s !important; } }
	@media (max-width: 640px) { .grid { grid-template-columns: 1fr; } .steps { grid-template-columns: repeat(3, 1fr); row-gap: 1.25rem; } .steps li:nth-child(3)::after { display: none; } }
</style>
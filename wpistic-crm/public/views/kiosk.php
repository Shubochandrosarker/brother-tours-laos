<?php
/**
 * Kiosk view — self-service customer waiver and check-in.
 *
 * Loaded by G2A_CRM_Kiosk::maybe_render() when ?g2a_kiosk=1 is hit.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kiosk_nonce   = wp_create_nonce( G2A_CRM_Kiosk::NONCE_ACTION );
$rest_root     = esc_url_raw( rest_url( G2A_CRM_REST_NAMESPACE ) );
$business_name = (string) G2A_CRM_Settings::get( 'business_name', 'WPistic' );
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $business_name ); ?> — Check In</title>
	<style>
		* { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; background: #080A0D; color: #F5F7FA; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; height: 100%; }
		.k-wrap { max-width: 720px; margin: 0 auto; padding: 32px 24px 80px; min-height: 100vh; }
		.k-brand { color: #D6A84F; font-size: 28px; letter-spacing: 1px; text-transform: uppercase; text-align: center; margin: 0 0 4px; font-weight: 700; }
		.k-tag { color: #AAB4C0; text-align: center; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 36px; }
		.k-card { background: #11161C; border: 1px solid #2A333D; border-radius: 12px; padding: 28px 24px; box-shadow: 0 12px 36px rgba(0,0,0,0.35); }
		.k-step-label { color: #AAB4C0; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px; }
		.k-step-title { font-size: 22px; margin: 0 0 20px; color: #F5F7FA; }
		.k-field { margin-bottom: 18px; }
		.k-field label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #AAB4C0; margin-bottom: 6px; }
		.k-input { width: 100%; padding: 14px 14px; background: #080A0D; border: 1px solid #2A333D; border-radius: 8px; color: #F5F7FA; font-size: 18px; font-family: inherit; }
		.k-input:focus { outline: none; border-color: #D6A84F; }
		.k-btn { display: inline-block; padding: 14px 24px; border-radius: 8px; border: none; background: #D6A84F; color: #1A1409; font-weight: 700; font-size: 16px; cursor: pointer; letter-spacing: 0.5px; text-transform: uppercase; width: 100%; }
		.k-btn:hover { background: #C09542; }
		.k-btn.secondary { background: transparent; color: #AAB4C0; border: 1px solid #2A333D; }
		.k-btn.secondary:hover { color: #F5F7FA; border-color: #D6A84F; }
		.k-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
		.k-error { background: rgba(217,65,65,0.12); color: #FF8888; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; border: 1px solid rgba(217,65,65,0.4); }
		.k-ok { background: rgba(51,194,127,0.12); color: #33C27F; padding: 16px; border-radius: 8px; margin-bottom: 20px; font-size: 15px; border: 1px solid rgba(51,194,127,0.4); }
		.k-section { font-size: 13px; color: #AAB4C0; line-height: 1.6; margin-bottom: 20px; max-height: 220px; overflow: auto; padding: 14px; background: #080A0D; border: 1px solid #2A333D; border-radius: 8px; }
		.k-sig-box { background: #fff; border: 1px solid #2A333D; border-radius: 8px; height: 200px; cursor: crosshair; display: block; touch-action: none; }
		.k-sig-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
		.k-sig-clear { background: none; border: none; color: #4DA3FF; cursor: pointer; font-size: 13px; padding: 4px 8px; }
		.k-bigsuccess { text-align: center; padding: 40px 20px; }
		.k-bigsuccess .check { font-size: 80px; color: #33C27F; }
		.k-footer { text-align: center; color: #555; font-size: 11px; margin-top: 40px; letter-spacing: 1px; }
	</style>
</head>
<body>
	<div class="k-wrap">
		<h1 class="k-brand"><?php echo esc_html( $business_name ); ?></h1>
		<div class="k-tag">Self-Service Check-In</div>

		<div id="k-screen-lookup" class="k-card">
			<div class="k-step-label">Step 1 of 2</div>
			<h2 class="k-step-title">Welcome — let's find your profile</h2>
			<div id="k-lookup-error" class="k-error" style="display:none;"></div>
			<div class="k-field">
				<label>Phone number</label>
				<input type="tel" id="k-phone" class="k-input" placeholder="(555) 123-4567" inputmode="tel" autocomplete="off" />
			</div>
			<div class="k-field">
				<label>Email address (optional)</label>
				<input type="email" id="k-email" class="k-input" placeholder="you@example.com" autocomplete="off" />
			</div>
			<div id="k-new-fields" style="display:none;">
				<div class="k-row">
					<div class="k-field">
						<label>First name</label>
						<input type="text" id="k-first" class="k-input" autocomplete="off" />
					</div>
					<div class="k-field">
						<label>Last name</label>
						<input type="text" id="k-last" class="k-input" autocomplete="off" />
					</div>
				</div>
			</div>
			<button class="k-btn" id="k-lookup-btn">Continue →</button>
		</div>

		<div id="k-screen-waiver" class="k-card" style="display:none;">
			<div class="k-step-label">Step 2 of 2 — Waiver</div>
			<h2 class="k-step-title">Sign your waiver</h2>
			<div id="k-waiver-error" class="k-error" style="display:none;"></div>

			<div class="k-section">
				By signing this waiver I acknowledge that participating in range, training, and related activities at <strong><?php echo esc_html( $business_name ); ?></strong> involves inherent risk, including the risk of serious injury or death. I confirm that I am legally permitted to handle firearms. I agree to follow all safety rules and staff instructions. I release <?php echo esc_html( $business_name ); ?> and its staff from claims arising out of my participation. I authorize the business to retain this waiver record.
			</div>

			<div class="k-field">
				<label>Type your full legal name</label>
				<input type="text" id="k-typed" class="k-input" autocomplete="off" />
			</div>

			<div class="k-field">
				<label>Sign below with your finger or mouse</label>
				<canvas id="k-sig" class="k-sig-box" width="640" height="200"></canvas>
				<div class="k-sig-actions">
					<button class="k-sig-clear" id="k-sig-clear">Clear signature</button>
					<small style="color:#666">Required if you don't type your full name</small>
				</div>
			</div>

			<button class="k-btn" id="k-sign-btn">Submit waiver</button>
			<button class="k-btn secondary" id="k-back-btn" style="margin-top:10px;">← Back</button>
		</div>

		<div id="k-screen-done" class="k-card" style="display:none;">
			<div class="k-bigsuccess">
				<div class="check">✓</div>
				<h2 class="k-step-title">You're all set</h2>
				<p style="color:#AAB4C0;">Please see a staff member to complete your check-in.</p>
				<p style="color:#555; font-size:12px;">Waiver record: <span id="k-done-id"></span></p>
				<button class="k-btn" id="k-restart-btn" style="margin-top: 24px;">Start over (new customer)</button>
			</div>
		</div>

		<div class="k-footer">Staff terminal · session resets after each customer</div>
	</div>

	<script>
		const BOOT = {
			restRoot: <?php echo wp_json_encode( $rest_root ); ?>,
			nonce: <?php echo wp_json_encode( $kiosk_nonce ); ?>,
		};
		let state = { customerId: null };

		function show(screen) {
			['lookup','waiver','done'].forEach(s => {
				document.getElementById('k-screen-' + s).style.display = s === screen ? 'block' : 'none';
			});
		}

		async function call(path, body) {
			const res = await fetch(BOOT.restRoot + path, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-G2A-Kiosk-Nonce': BOOT.nonce,
				},
				body: JSON.stringify(body || {}),
			});
			const text = await res.text();
			let data = null;
			try { data = text ? JSON.parse(text) : null; } catch (e) {}
			if (!res.ok) {
				const err = new Error(data?.message || 'Request failed');
				err.data = data;
				err.status = res.status;
				throw err;
			}
			return data;
		}

		function showError(elId, msg) {
			const el = document.getElementById(elId);
			el.textContent = msg;
			el.style.display = 'block';
		}

		// Step 1: Lookup
		document.getElementById('k-lookup-btn').addEventListener('click', async () => {
			document.getElementById('k-lookup-error').style.display = 'none';
			const phone = document.getElementById('k-phone').value.trim();
			const email = document.getElementById('k-email').value.trim();
			const first = document.getElementById('k-first').value.trim();
			const last = document.getElementById('k-last').value.trim();

			if (!phone && !email) {
				return showError('k-lookup-error', 'Please enter your phone number or email.');
			}

			try {
				const res = await call('/kiosk/check-in', { phone, email, first_name: first, last_name: last });
				state.customerId = res.customer.id;
				state.checkinToken = res.checkin_token;
				show('waiver');
			} catch (e) {
				if (e.data?.code === 'g2a_crm_kiosk_new_customer') {
					document.getElementById('k-new-fields').style.display = 'block';
					return showError('k-lookup-error', e.data.message || 'Please provide your name.');
				}
				showError('k-lookup-error', e.message);
			}
		});

		// Signature canvas
		const canvas = document.getElementById('k-sig');
		const ctx = canvas.getContext('2d');
		let drawing = false, hasInk = false;
		ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#111';

		function pos(e) {
			const r = canvas.getBoundingClientRect();
			const t = e.touches ? e.touches[0] : e;
			return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) };
		}
		function start(e) { e.preventDefault(); drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
		function move(e) { if (!drawing) return; e.preventDefault(); const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); hasInk = true; }
		function end() { drawing = false; }

		canvas.addEventListener('mousedown', start);
		canvas.addEventListener('mousemove', move);
		canvas.addEventListener('mouseup', end);
		canvas.addEventListener('mouseleave', end);
		canvas.addEventListener('touchstart', start);
		canvas.addEventListener('touchmove', move);
		canvas.addEventListener('touchend', end);

		document.getElementById('k-sig-clear').addEventListener('click', (e) => {
			e.preventDefault();
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			hasInk = false;
		});

		// Step 2: Submit waiver
		document.getElementById('k-sign-btn').addEventListener('click', async () => {
			document.getElementById('k-waiver-error').style.display = 'none';
			const typed = document.getElementById('k-typed').value.trim();
			const sig = hasInk ? canvas.toDataURL('image/png') : '';
			if (!typed && !sig) {
				return showError('k-waiver-error', 'Please type your name or sign before submitting.');
			}
			try {
				const res = await call('/kiosk/waiver', {
					customer_id: state.customerId,
					checkin_token: state.checkinToken,
					waiver_type: 'range',
					typed_name: typed,
					signature_image: sig,
				});
				document.getElementById('k-done-id').textContent = '#' + res.waiver_id;
				show('done');
			} catch (e) {
				showError('k-waiver-error', e.message);
			}
		});

		document.getElementById('k-back-btn').addEventListener('click', () => show('lookup'));
		document.getElementById('k-restart-btn').addEventListener('click', () => {
			['k-phone','k-email','k-first','k-last','k-typed'].forEach(id => document.getElementById(id).value = '');
			document.getElementById('k-new-fields').style.display = 'none';
			ctx.clearRect(0, 0, canvas.width, canvas.height); hasInk = false;
			state.customerId = null;
			show('lookup');
		});
	</script>
</body>
</html>

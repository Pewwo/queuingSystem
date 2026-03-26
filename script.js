// ============================================
// DASHBOARD LOGIC (Real-time via Polling)
// ============================================

let dashboardInterval = null;
let previousQueueState = {}; // Storage for tracking changes
let isVoiceEnabled = true; // Enabled by default now

// Handle browser 'user gesture' requirement
document.addEventListener('click', () => {
    if (window.speechSynthesis) {
        const prime = new SpeechSynthesisUtterance("");
        window.speechSynthesis.speak(prime);
    }
}, { once: true });

function toggleHeader() {
    const header = document.getElementById('main-header');
    const showBtn = document.getElementById('show-header-btn');
    if (header.classList.contains('hidden')) {
        header.classList.remove('hidden');
        showBtn.classList.add('hidden');
    } else {
        header.classList.add('hidden');
        showBtn.classList.remove('hidden');
    }
}

// Inactivity/Idle Hiding Logic for "TV Mode"
let idleTimeout;

function resetIdleTimer() {
    const showBtn = document.getElementById('show-header-btn');
    const hideBtn = document.getElementById('hide-header-btn');

    // Show arrows when active
    if (showBtn) {
        showBtn.style.opacity = "1";
        showBtn.style.pointerEvents = "auto";
    }
    if (hideBtn) {
        hideBtn.style.opacity = "1";
        hideBtn.style.pointerEvents = "auto";
    }

    clearTimeout(idleTimeout);
    idleTimeout = setTimeout(() => {
        // Hide only the arrows when idle (after 3 seconds)
        if (showBtn) {
            showBtn.style.opacity = "0";
            showBtn.style.pointerEvents = "none";
        }
        if (hideBtn) {
            hideBtn.style.opacity = "0";
            hideBtn.style.pointerEvents = "none";
        }
    }, 3000);
}

document.addEventListener('mousemove', resetIdleTimer);
document.addEventListener('mousedown', resetIdleTimer);
document.addEventListener('keydown', resetIdleTimer);
document.addEventListener('scroll', resetIdleTimer);

// Initial start
resetIdleTimer();

// Function to call a number
function announceQueue(number, clinic) {
    if (!('speechSynthesis' in window) || !isVoiceEnabled) return;

    // NOTE: We no longer call window.speechSynthesis.cancel() 
    // This allows the browser to queue multiple announcements sequentially.

    // Wait for voices to be loaded
    const voices = window.speechSynthesis.getVoices();
    // Try to find a premium female voice (Google, Samantha, Zira etc.)
    const preferredVoice = voices.find(v =>
        (v.name.includes('Google') && v.name.includes('English') && v.name.includes('Female')) ||
        v.name.includes('Samantha') ||
        v.name.includes('Victoria') ||
        v.name.includes('Zira') ||
        v.name.includes('Microsoft English')
    ) || voices.find(v => v.lang.includes('en') && (v.name.includes('Female') || v.name.includes('Female'))) || voices[0];

    const createUtterance = (text) => {
        const msg = new SpeechSynthesisUtterance();
        msg.voice = preferredVoice;
        msg.text = text;
        msg.rate = 0.85;
        msg.pitch = 1.05;
        msg.volume = 1;
        return msg;
    };

    let textToSpeak = `Patient number ${number}, please proceed to ${clinic}.`;

    // Queue the announcement TWICE for twice-repeat behavior
    window.speechSynthesis.speak(createUtterance(textToSpeak));

    // Small silent utterance for a pause between repetitions
    const pause = createUtterance("");
    pause.volume = 0;
    window.speechSynthesis.speak(pause);

    window.speechSynthesis.speak(createUtterance(textToSpeak));
}

function startDashboardPolling() {
    fetchDashboard();
    if (dashboardInterval) clearInterval(dashboardInterval);
    dashboardInterval = setInterval(fetchDashboard, 3000);
}

async function fetchDashboard() {
    try {
        const res = await fetch('api.php?action=dashboard');
        const data = await res.json();
        renderDashboard(data);
    } catch (e) {
        console.error('Error fetching dashboard', e);
    }
}

function renderDashboard(data) {
    const grid = document.getElementById('dashboard-grid');
    if (!grid) return;

    grid.innerHTML = '';
    if (!data || data.length === 0) {
        grid.innerHTML = '<div class="col-span-full text-center py-10 text-slate-500 text-xl font-medium">No clinics found. Please run setup.</div>';
        return;
    }

    data.forEach(item => {
        const isServing = item.status === 'serving';

        // Voice Announcement Logic
        if (isServing && item.queue_number) {
            const clinicKey = `clinic_${item.clinic_number}`;
            // If we have a previous recorded number and it just changed, announce it!
            if (previousQueueState[clinicKey] !== undefined && previousQueueState[clinicKey] !== item.queue_number) {
                announceQueue(item.queue_number, item.clinic_number);
            }
            // Update the state for next poll
            previousQueueState[clinicKey] = item.queue_number;
        } else if (!isServing) {
            // If the cabin becomes vacant, clear its tracking so it can announce the first patient later
            previousQueueState[`clinic_${item.clinic_number}`] = null;
        }

        const card = document.createElement('div');

        const baseClasses = 'bg-white rounded-xl p-3 shadow-md border-t-4 relative overflow-hidden transition-all transform hover:shadow-lg h-[220px] flex flex-col';
        const statusClasses = isServing ? 'border-blue-600' : 'border-slate-200 opacity-80';

        card.className = `${baseClasses} ${statusClasses}`;

        card.innerHTML = `
            <div class="flex justify-between items-start mb-3 shrink-0">
                <div>
                    <div class="text-lg font-black text-slate-900 tracking-tighter leading-none mb-1">${item.clinic_number}</div>
                    <div class="flex items-center gap-1 min-h-[12px]">
                        <div class="w-1 h-1 rounded-full ${isServing ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'}"></div>
                        <div class="text-slate-500 font-bold uppercase tracking-widest text-[7px] truncate max-w-[60px]">${item.doctor_name || 'No Doctor'}</div>
                    </div>
                </div>
                <div class="bg-blue-50 px-1.5 py-0.5 rounded-lg border border-blue-100 flex items-center gap-1">
                    <span class="text-blue-600 font-black text-xs">${item.waiting_count}</span>
                    <span class="text-[6px] font-bold text-slate-400 uppercase tracking-tighter">Wait</span>
                </div>
            </div>

            <div class="flex items-stretch gap-0 flex-grow border border-slate-100 rounded-xl overflow-hidden bg-slate-50/30">
                <!-- Left: Now Serving (Vertical Split) -->
                <div class="flex-1 p-2 flex flex-col items-center justify-center border-r border-slate-100 text-center">
                    <div class="text-[7px] font-black text-slate-400 uppercase tracking-[0.1em] mb-2">NOW</div>
                    ${isServing
                ? `
                            <div class="text-2xl font-black text-blue-600 leading-none mb-1">${item.queue_number}</div>
                            <div class="text-[9px] font-bold text-slate-700 truncate w-full px-1">${item.patient_name}</div>
                          `
                : `<div class="text-[10px] font-black text-[10px] text-slate-300 uppercase italic">Vacant</div>`
            }
                </div>

                <!-- Right: Waiting List (Vertical Split) -->
                <div class="flex-1 p-2 flex flex-col bg-white">
                    <div class="text-[7px] font-black text-slate-400 uppercase tracking-[0.1em] mb-2 text-center">NEXT</div>
                    <div class="grid grid-cols-2 gap-1 justify-center max-h-[100px] overflow-hidden">
                        ${item.waiting_list ? item.waiting_list.split(',').slice(0, 6).map(num => `
                            <span class="px-1.5 py-0.5 bg-slate-50 border border-slate-100 rounded text-[8px] font-black text-slate-500 text-center">
                                ${num}
                            </span>
                        `).join('') : '<div class="col-span-2 text-center text-[7px] italic text-slate-300">None</div>'}
                    </div>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

// ============================================
// REGISTRATION LOGIC
// ============================================
if (document.getElementById('registrationForm')) {
    const docSelect = document.getElementById('doctor_id');
    const clinInput = document.getElementById('clinic_id');
    const clinManual = document.getElementById('clinic_id_manual');
    const clinDisplay = document.getElementById('clinic_display');
    const clinLabel = document.getElementById('clinic_label');
    const clinText = document.getElementById('clinic_name_text');
    const clinIndicator = document.getElementById('clinic_status_indicator');

    const updateClinicForDoctor = async (dId) => {
        // Reset view
        clinDisplay.classList.remove('hidden');
        clinManual.classList.add('hidden');
        clinLabel.innerText = 'Active Clinic Location';

        if (!dId) {
            clinText.innerText = 'Select a doctor first';
            clinText.classList.remove('text-blue-600', 'text-amber-600', 'text-rose-500');
            clinText.classList.add('text-slate-400');
            clinIndicator.className = 'w-2.5 h-2.5 rounded-full bg-slate-300';
            clinInput.value = '';
            return;
        }

        clinText.innerText = 'Detecting Clinic...';
        clinIndicator.className = 'w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse';

        try {
            const res = await fetch(`api.php?action=get_doctor_clinic&doctor_id=${dId}`);
            const data = await res.json();

            if (data.success && data.clinic_id) {
                clinText.innerText = data.clinic_name;
                clinText.classList.remove('text-slate-400', 'text-amber-600', 'text-rose-500');
                clinText.classList.add('text-blue-600');
                clinIndicator.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]';
                clinInput.value = data.clinic_id;
            } else if (typeof terminalClinicId !== 'undefined' && terminalClinicId) {
                // Fallback to terminal clinic if doctor not logged in
                clinText.innerText = terminalClinicName + " (Your Terminal)";
                clinText.classList.remove('text-blue-600', 'text-slate-400', 'text-rose-500');
                clinText.classList.add('text-amber-600');
                clinIndicator.className = 'w-2.5 h-2.5 rounded-full bg-amber-500';
                clinInput.value = terminalClinicId;
            } else {
                // No current clinic found, and no terminal clinic either
                clinText.innerText = 'Assigned to Doctor (Clinic Pending)';
                clinText.classList.remove('text-blue-600', 'text-amber-600');
                clinText.classList.add('text-slate-500');
                clinIndicator.className = 'w-2.5 h-2.5 rounded-full bg-slate-400';
                clinInput.value = '0'; // Will be stored as NULL in DB
            }
        } catch (e) {
            console.error('Error fetching doctor clinic', e);
            clinText.innerText = 'System Error';
            clinIndicator.className = 'w-2.5 h-2.5 rounded-full bg-rose-500';
        }
    };

    if (docSelect && clinInput) {
        docSelect.addEventListener('change', () => updateClinicForDoctor(docSelect.value));

        // Run on load if a doctor is pre-selected
        if (docSelect.value) {
            updateClinicForDoctor(docSelect.value);
        } else {
            clinText.innerText = 'Select a doctor first';
            clinText.classList.add('text-slate-400');
        }
    }

    document.getElementById('registrationForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const dId = document.getElementById('doctor_id').value;
        const cId = document.getElementById('clinic_id').value;

        try {
            const res = await fetch('api.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    doctor_id: parseInt(dId),
                    clinic_id: parseInt(cId)
                })
            });
            const data = await res.json();
            const msgBox = document.getElementById('reg-result');
            msgBox.classList.remove('hidden');
            if (data.success) {
                msgBox.className = 'p-6 rounded-2xl text-center bg-emerald-50 text-emerald-800 border-2 border-emerald-200 mt-8';
                msgBox.innerHTML = `<h3 class="font-black text-xl mb-2 text-emerald-700 uppercase tracking-widest">Success</h3><p class="font-medium">Queue Number</p><strong class="text-5xl font-black block mt-2 text-emerald-600 drop-shadow-sm">${data.queue_number}</strong>`;
                document.getElementById('registrationForm').reset();
            } else {
                msgBox.className = 'p-6 rounded-2xl text-center bg-red-50 text-red-800 border-2 border-red-200 mt-8 font-bold';
                msgBox.innerText = 'Error: ' + data.error;
            }
        } catch (e) {
            console.error('Registration failed', e);
        }
    });
}

// ============================================
// DOCTOR PORTAL LOGIC (Real-time via Polling)
// ============================================

let activeClinicId = null;
let doctorInterval = null;

// Only enable if on the doctor page
if (document.getElementById('doctor-main')) {
    activeClinicId = typeof userRelationId !== 'undefined' ? userRelationId : null;
    const clinicName = typeof userClinicName !== 'undefined' ? userClinicName : 'Unknown Clinic';

    // Auto-reveal the panel regardless (since sidebar is gone)
    const panel = document.getElementById('doctor-panel');
    if (panel) {
        panel.classList.remove('hidden');
        document.getElementById('current-clinic-name').innerText = clinicName;

        if (activeClinicId && activeClinicId !== 'null') {
            startDoctorPolling();
        } else {
            document.getElementById('clinic-state-badge').innerText = 'NO CLINIC ASSIGNED';
            document.getElementById('clinic-state-badge').className = 'px-5 py-2 rounded-full text-sm font-black tracking-widest uppercase bg-red-100 text-red-800 border border-red-200';
        }
    }
}

function startDoctorPolling() {
    fetchDoctorState();
    if (doctorInterval) clearInterval(doctorInterval);
    doctorInterval = setInterval(fetchDoctorState, 3000);
}

async function fetchDoctorState() {
    if (!activeClinicId || activeClinicId === 'null') return;
    try {
        const res = await fetch(`api.php?action=doctor_state&clinic_id=${activeClinicId}`);
        const data = await res.json();
        renderDoctorState(data);
    } catch (e) {
        console.error('Failed state fetch', e);
    }
}

function renderDoctorState(data) {
    if (!data || !data.clinic_status) return;

    const badge = document.getElementById('clinic-state-badge');
    badge.innerText = data.clinic_status.toUpperCase();

    const markDoneBtn = document.getElementById('btn-mark-done');
    const callNextBtn = document.getElementById('btn-call-next');

    if (data.clinic_status === 'serving') {
        badge.className = 'px-5 py-2 rounded-full text-sm font-black tracking-widest uppercase bg-blue-100 text-blue-800 border border-blue-200';
        document.getElementById('serving-num').innerText = data.queue_number;
        document.getElementById('serving-name').innerText = data.patient_name;
        if (markDoneBtn) markDoneBtn.disabled = false;
    } else {
        badge.className = 'px-5 py-2 rounded-full text-sm font-black tracking-widest uppercase bg-slate-100 text-slate-600 border border-slate-200';
        document.getElementById('serving-num').innerText = '--';
        document.getElementById('serving-name').innerText = 'No patient serving';
        if (markDoneBtn) markDoneBtn.disabled = true;
    }
}

async function manageAction(actionUrl) {
    if (!activeClinicId) return;
    try {
        const res = await fetch(actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ clinic_id: activeClinicId })
        });
        const data = await res.json();
        const msgBox = document.getElementById('action-msg');

        if (data.success) {
            msgBox.classList.remove('hidden');
            msgBox.className = 'mt-8 p-5 rounded-xl text-center font-bold text-lg bg-emerald-50 text-emerald-800 border-2 border-emerald-200';
            msgBox.innerText = data.message || 'Action success';
            // fetchDoctorState is no longer needed manually, SSE will push the update instantly!

            // hide message after 3 secs
            setTimeout(() => msgBox.classList.add('hidden'), 3000);
        } else {
            msgBox.classList.remove('hidden');
            msgBox.className = 'mt-8 p-5 rounded-xl text-center font-bold text-lg bg-red-50 text-red-800 border-2 border-red-200';
            msgBox.innerText = data.error || 'Error performing action';
        }
    } catch (e) {
        console.error('Action failed', e);
    }
}

function openCallNextModal() {
    const modal = document.getElementById('call-next-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeCallNextModal() {
    const modal = document.getElementById('call-next-modal');
    if (modal) modal.classList.add('hidden');
}

function confirmCallNext() {
    closeCallNextModal();
    manageAction('api.php?action=call_next');
}

function markDone() { manageAction('api.php?action=mark_done'); }

// ============================================
// PROFILE SETTINGS LOGIC
// ============================================

window.addEventListener('DOMContentLoaded', () => {
    // Add input listeners for profile
    ['settings-first-name', 'settings-last-name', 'settings-username'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', checkProfileChanges);
    });

    // Add input listeners for password
    ['pass-old', 'pass-new'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', checkPasswordChanges);
    });
});

function openSettingsModal() {
    const modal = document.getElementById('settings-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeSettingsModal() {
    const modal = document.getElementById('settings-modal');
    if (modal) modal.classList.add('hidden');
}

function toggleSettingsPass(inputId = 'settings-password', openId = 'eye-open', closedId = 'eye-closed') {
    const input = document.getElementById(inputId);
    const openIcon = document.getElementById(openId);
    const closedIcon = document.getElementById(closedId);
    if (input.type === 'password') {
        input.type = 'text';
        if (openIcon) openIcon.classList.add('hidden');
        if (closedIcon) closedIcon.classList.remove('hidden');
    } else {
        input.type = 'password';
        if (closedIcon) closedIcon.classList.add('hidden');
        if (openIcon) openIcon.classList.remove('hidden');
    }
}

// ============================================
// UNIVERSAL ALERT MODAL LOGIC
// ============================================

function showAlert(message, type = 'error') {
    const modal = document.getElementById('universal-alert-modal');
    const title = document.getElementById('alert-title');
    const msg = document.getElementById('alert-message');
    const iconContainer = document.getElementById('alert-icon-container');
    const btn = document.getElementById('alert-btn');

    if (!modal) return;

    msg.innerText = message;

    if (type === 'success') {
        title.innerText = 'Success';
        title.className = 'text-2xl font-black text-emerald-600 mb-3';
        iconContainer.className = 'w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-6 mx-auto';
        iconContainer.innerHTML = '<svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
        btn.className = 'w-full py-4 px-6 rounded-2xl font-black text-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 bg-emerald-600 hover:bg-emerald-700 text-white';
    } else {
        title.innerText = 'Attention';
        title.className = 'text-2xl font-black text-rose-600 mb-3';
        iconContainer.className = 'w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mb-6 mx-auto';
        iconContainer.innerHTML = '<svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
        btn.className = 'w-full py-4 px-6 rounded-2xl font-black text-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 bg-rose-600 hover:bg-rose-700 text-white';
    }

    modal.classList.remove('hidden');
}

function closeAlert() {
    const modal = document.getElementById('universal-alert-modal');
    if (modal) modal.classList.add('hidden');
}

let initialProfile = { first: '', last: '', user: '' };

function openSettingsModal() {
    const modal = document.getElementById('settings-modal');
    if (!modal) return;

    // Capture initial state
    initialProfile.first = document.getElementById('settings-first-name').value;
    initialProfile.last = document.getElementById('settings-last-name').value;
    initialProfile.user = document.getElementById('settings-username').value;

    // Initial button state
    checkProfileChanges();

    modal.classList.remove('hidden');
}

function checkProfileChanges() {
    const fn = document.getElementById('settings-first-name').value.trim();
    const ln = document.getElementById('settings-last-name').value.trim();
    const un = document.getElementById('settings-username').value.trim();
    const btn = document.getElementById('save-profile-btn');

    const isChanged = (fn !== initialProfile.first || ln !== initialProfile.last || un !== initialProfile.user);
    const isValid = fn && ln && un;

    if (isChanged && isValid) {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function openPasswordModal() {
    closeSettingsModal();
    const modal = document.getElementById('password-modal');
    if (!modal) return;

    // Clear fields
    document.getElementById('pass-old').value = '';
    document.getElementById('pass-new').value = '';

    checkPasswordChanges();
    modal.classList.remove('hidden');
}

function checkPasswordChanges() {
    const oldP = document.getElementById('pass-old').value;
    const newP = document.getElementById('pass-new').value;
    const btn = document.getElementById('save-pass-btn');

    if (oldP && newP) {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function closePasswordModal() {
    const modal = document.getElementById('password-modal');
    if (modal) modal.classList.add('hidden');
}

async function savePassword(e) {
    if (e) e.preventDefault();
    const oldPass = document.getElementById('pass-old').value;
    const newPass = document.getElementById('pass-new').value;
    const btn = document.getElementById('save-pass-btn');

    if (!oldPass || !newPass) {
        showAlert("Both current and new passwords are required.");
        return;
    }

    const originalText = btn.innerHTML;
    btn.innerHTML = 'Updating...';
    btn.disabled = true;

    try {
        const fn = document.getElementById('settings-first-name')?.value || '';
        const ln = document.getElementById('settings-last-name')?.value || '';
        const un = document.getElementById('settings-username')?.value || '';

        const res = await fetch('api.php?action=update_doctor', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: userDoctorId,
                userId: userId,
                old_password: oldPass,
                password: newPass,
                firstName: fn,
                lastName: ln,
                username: un
            })
        });
        const data = await res.json();
        if (data.success) {
            showAlert("Password updated successfully!", "success");
            closePasswordModal();
        } else {
            showAlert(data.error);
        }
    } catch (err) {
        showAlert("Error: " + err.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function saveProfile(e) {
    if (e) e.preventDefault();

    const firstName = document.getElementById('settings-first-name').value.trim();
    const lastName = document.getElementById('settings-last-name').value.trim();
    const user = document.getElementById('settings-username').value.trim();
    const btn = document.getElementById('save-profile-btn');

    if (!firstName || !lastName || !user) {
        showAlert("First Name, Last Name, and Username are required.");
        return;
    }

    const fullName = "Dr. " + firstName + " " + lastName;
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Saving...';
    btn.disabled = true;

    try {
        const res = await fetch('api.php?action=update_doctor', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: userDoctorId,
                userId: userId,
                firstName: firstName,
                lastName: lastName,
                username: user,
                skip_password_check: true
            })
        });
        const data = await res.json();

        if (data.success) {
            showAlert("Profile updated successfully.", "success");
            setTimeout(() => {
                closeSettingsModal();
                window.location.reload();
            }, 1000);
        } else {
            showAlert(data.error);
        }
    } catch (err) {
        showAlert("Connection error: " + err.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

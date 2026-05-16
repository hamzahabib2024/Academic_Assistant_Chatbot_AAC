/* ═══════════════════════════════════════════════════════
   AAC — AI Academic Assistant Chatbot
   JavaScript · PHP Backend Connected Version
   ═══════════════════════════════════════════════════════ */

'use strict';

// ─── CONFIG ───────────────────────────────────────────────
// Adjust this path if you rename the project folder in htdocs
const API_BASE = 'api';

// ─── STATE ────────────────────────────────────────────────
const state = {
  currentUser:    null,
  token:          null,
  currentSession: null,
  chatHistory:    [],
  questionCount:  0,
  fileCount:      0,
};

// ─── API HELPER ───────────────────────────────────────────
async function api(endpoint, options = {}) {
  const headers = { 'Content-Type': 'application/json' };
  if (state.token) headers['Authorization'] = 'Bearer ' + state.token;

  try {
    const res = await fetch(`${API_BASE}/${endpoint}`, {
      headers,
      ...options,
    });
    const data = await res.json();
    return data;
  } catch (err) {
    console.error('API error:', err);
    return { success: false, message: 'Network error. Is XAMPP running?' };
  }
}

// Multipart (for file upload — no JSON Content-Type)
async function apiUpload(endpoint, formData) {
  const headers = {};
  if (state.token) headers['Authorization'] = 'Bearer ' + state.token;
  try {
    const res = await fetch(`${API_BASE}/${endpoint}`, { method: 'POST', headers, body: formData });
    return await res.json();
  } catch (err) {
    return { success: false, message: 'Upload failed.' };
  }
}

// ─── CANVAS PARTICLES ─────────────────────────────────────
function initCanvas() {
  const canvas = document.getElementById('bg-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  function resize() {
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  const particles = Array.from({ length: 70 }, () => ({
    x: Math.random() * canvas.width,
    y: Math.random() * canvas.height,
    r: Math.random() * 1.5 + 0.3,
    vx: (Math.random() - 0.5) * 0.3,
    vy: (Math.random() - 0.5) * 0.3,
    alpha: Math.random() * 0.5 + 0.1,
  }));

  const orbs = [
    { x: 0.15, y: 0.3, r: 220, color: 'rgba(124,106,245,0.07)' },
    { x: 0.85, y: 0.6, r: 180, color: 'rgba(232,121,249,0.05)' },
    { x: 0.5,  y: 0.8, r: 150, color: 'rgba(56,189,248,0.04)'  },
  ];

  let t = 0;
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    t += 0.008;

    orbs.forEach((orb, i) => {
      const ox = orb.x * canvas.width  + Math.sin(t + i) * 30;
      const oy = orb.y * canvas.height + Math.cos(t * 0.7 + i) * 20;
      const grad = ctx.createRadialGradient(ox, oy, 0, ox, oy, orb.r);
      grad.addColorStop(0, orb.color);
      grad.addColorStop(1, 'transparent');
      ctx.fillStyle = grad;
      ctx.beginPath(); ctx.arc(ox, oy, orb.r, 0, Math.PI * 2);
      ctx.fill();
    });

    particles.forEach(p => {
      p.x += p.vx; p.y += p.vy;
      if (p.x < 0) p.x = canvas.width;
      if (p.x > canvas.width) p.x = 0;
      if (p.y < 0) p.y = canvas.height;
      if (p.y > canvas.height) p.y = 0;
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(167,139,250,${p.alpha})`;
      ctx.fill();
    });

    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x;
        const dy = particles[i].y - particles[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 100) {
          ctx.beginPath();
          ctx.moveTo(particles[i].x, particles[i].y);
          ctx.lineTo(particles[j].x, particles[j].y);
          ctx.strokeStyle = `rgba(124,106,245,${0.08 * (1 - dist / 100)})`;
          ctx.lineWidth = 0.5;
          ctx.stroke();
        }
      }
    }
    requestAnimationFrame(draw);
  }
  draw();
}

// ─── CURSOR ───────────────────────────────────────────────
function initCursor() {
  const dot  = document.querySelector('.cursor-dot');
  const ring = document.querySelector('.cursor-ring');
  let mx = 0, my = 0, rx = 0, ry = 0;

  document.addEventListener('mousemove', e => {
    mx = e.clientX; my = e.clientY;
    dot.style.left = mx + 'px'; dot.style.top = my + 'px';
  });

  function animRing() {
    rx += (mx - rx) * 0.12;
    ry += (my - ry) * 0.12;
    ring.style.left = rx + 'px'; ring.style.top = ry + 'px';
    requestAnimationFrame(animRing);
  }
  animRing();

  document.addEventListener('mousedown', () => {
    dot.style.transform  = 'translate(-50%,-50%) scale(0.6)';
    ring.style.width = '20px'; ring.style.height = '20px';
  });
  document.addEventListener('mouseup', () => {
    dot.style.transform  = 'translate(-50%,-50%) scale(1)';
    ring.style.width = '32px'; ring.style.height = '32px';
  });

  document.querySelectorAll('button, a, input, textarea, .s-chip, .kb-card').forEach(el => {
    el.addEventListener('mouseenter', () => {
      ring.style.width = '50px'; ring.style.height = '50px';
      ring.style.borderColor = 'rgba(167,139,250,0.8)';
      dot.style.opacity = '0';
    });
    el.addEventListener('mouseleave', () => {
      ring.style.width = '32px'; ring.style.height = '32px';
      ring.style.borderColor = 'rgba(167,139,250,0.5)';
      dot.style.opacity = '1';
    });
  });
}

// ─── PAGE ROUTING ─────────────────────────────────────────
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const page = document.getElementById(id);
  if (page) page.classList.add('active');
  window.scrollTo(0, 0);
  if (id === 'landing') setTimeout(initCanvas, 50);
}

// ─── AUTH — REGISTER ──────────────────────────────────────
async function doRegister() {
  const name   = document.getElementById('reg-name').value.trim();
  const email  = document.getElementById('reg-email').value.trim();
  const pass   = document.getElementById('reg-pass').value;
  const cpass  = document.getElementById('reg-cpass').value;
  const msgEl  = document.getElementById('reg-msg');

  msgEl.className = 'form-msg';
  if (!name || !email || !pass || !cpass)
    return showMsg(msgEl, 'error', 'Please fill in all fields.');
  if (!email.includes('@'))
    return showMsg(msgEl, 'error', 'Enter a valid email address.');
  if (pass.length < 6)
    return showMsg(msgEl, 'error', 'Password must be at least 6 characters.');
  if (pass !== cpass)
    return showMsg(msgEl, 'error', 'Passwords do not match.');

  showMsg(msgEl, 'info', 'Creating account...');

  const data = await api('auth.php?action=register', {
    method: 'POST',
    body: JSON.stringify({ name, email, password: pass, confirm: cpass }),
  });

  if (!data.success) return showMsg(msgEl, 'error', data.message);

  showMsg(msgEl, 'success', '✓ Account created! Redirecting...');
  state.token = data.token;
  state.currentUser = data.user;
  localStorage.setItem('aac_token', data.token);

  setTimeout(() => loginUser(data.user), 1000);
}

// ─── AUTH — LOGIN ─────────────────────────────────────────
async function doLogin() {
  const email  = document.getElementById('login-email').value.trim();
  const pass   = document.getElementById('login-pass').value;
  const msgEl  = document.getElementById('login-msg');

  msgEl.className = 'form-msg';
  if (!email || !pass)
    return showMsg(msgEl, 'error', 'Please enter your email and password.');

  showMsg(msgEl, 'info', 'Signing in...');

  const data = await api('auth.php?action=login', {
    method: 'POST',
    body: JSON.stringify({ email, password: pass }),
  });

  if (!data.success) return showMsg(msgEl, 'error', data.message);

  showMsg(msgEl, 'success', '✓ Welcome back! Loading dashboard...');
  state.token = data.token;
  state.currentUser = data.user;
  localStorage.setItem('aac_token', data.token);

  setTimeout(() => loginUser(data.user), 700);
}

// ─── DEMO LOGIN ───────────────────────────────────────────
function demoLogin(role) {
  if (role === 'student') {
    document.getElementById('login-email').value = 'student@demo.com';
    document.getElementById('login-pass').value  = 'demo123';
  } else {
    document.getElementById('login-email').value = 'admin@demo.com';
    document.getElementById('login-pass').value  = 'admin123';
  }
  setTimeout(doLogin, 200);
}

// ─── LOGIN USER (route to dashboard) ─────────────────────
function loginUser(user) {
  state.currentUser = user;
  document.getElementById('login-msg').textContent = '';

  if (user.role === 'admin') {
    showPage('admin-dashboard');
    initAdminDashboard();
  } else {
    showPage('student-dashboard');
    initStudentDashboard();
  }
}

// ─── LOGOUT ───────────────────────────────────────────────
function doLogout() {
  state.currentUser    = null;
  state.token          = null;
  state.currentSession = null;
  state.chatHistory    = [];
  state.questionCount  = 0;
  localStorage.removeItem('aac_token');
  document.getElementById('chat-window').innerHTML = '';
  showPage('landing');
  showToast('You have been logged out.');
}

// ─── TOGGLE PASSWORD VISIBILITY ───────────────────────────
function togglePass(inputId, btn) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text'; btn.textContent = '🙈';
  } else {
    input.type = 'password'; btn.textContent = '👁';
  }
}

// ─── STUDENT DASHBOARD ────────────────────────────────────
function initStudentDashboard() {
  const user = state.currentUser;
  document.getElementById('sb-user-name').textContent = user.name;
  document.getElementById('cw-name').textContent      = user.name.split(' ')[0];
  document.getElementById('profile-name').textContent  = user.name;
  document.getElementById('profile-email').textContent = user.email;
  document.getElementById('pf-name').value  = user.name;
  document.getElementById('pf-email').value = user.email;

  const initials = user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  document.getElementById('profile-avatar').textContent = initials;

  resetChatWelcome();
  const chatLink = document.querySelector('#student-dashboard .sb-link');
  if (chatLink) switchTab(chatLink, 'chat-tab', 'student-dashboard');

  loadChatHistory();
  loadUploadedFiles();
}

function resetChatWelcome() {
  const user    = state.currentUser;
  const chatWin = document.getElementById('chat-window');
  chatWin.innerHTML = `
    <div class="chat-welcome">
      <div class="cw-icon">◈</div>
      <h3>Hello, ${user ? user.name.split(' ')[0] : 'there'}!</h3>
      <p>I'm your AI Academic Assistant. Ask me about any academic topic, subject, or concept.</p>
      <div class="suggestion-chips">
        <button class="s-chip" onclick="sendSuggestion(this)">Explain Machine Learning basics</button>
        <button class="s-chip" onclick="sendSuggestion(this)">What is the OSI model?</button>
        <button class="s-chip" onclick="sendSuggestion(this)">Summarize Big O notation</button>
        <button class="s-chip" onclick="sendSuggestion(this)">How does TCP/IP work?</button>
      </div>
    </div>`;
  state.currentSession = null;
}

// ─── ADMIN DASHBOARD ──────────────────────────────────────
async function initAdminDashboard() {
  loadAdminStats();
  loadUsersTable();
  loadKBEntries();
  loadChatLogs();
}

// ─── SIDEBAR / TAB ────────────────────────────────────────
function toggleSidebar()      { document.getElementById('sidebar').classList.toggle('collapsed'); }
function toggleAdminSidebar() { document.getElementById('admin-sidebar').classList.toggle('collapsed'); }

function switchTab(linkEl, tabId, dashId) {
  document.querySelectorAll(`#${dashId} .sb-link`).forEach(l => l.classList.remove('active'));
  linkEl.classList.add('active');
  document.querySelectorAll(`#${dashId} .tab-content`).forEach(t => t.classList.remove('active'));
  const target = document.getElementById(tabId);
  if (target) target.classList.add('active');
  return false;
}

// ─── CHAT — SEND MESSAGE ──────────────────────────────────
async function sendMessage() {
  const input = document.getElementById('chat-input');
  const text  = input.value.trim();
  if (!text) return;

  input.value = '';
  input.style.height = 'auto';

  const welcome = document.querySelector('.chat-welcome');
  if (welcome) welcome.remove();

  appendMessage('user', text);
  state.questionCount++;
  document.getElementById('stat-questions').textContent = state.questionCount;

  showTyping();

  const data = await api(`chat.php?action=send`, {
    method: 'POST',
    body: JSON.stringify({ message: text, session_id: state.currentSession }),
  });

  hideTyping();

  if (!data.success) {
    appendMessage('bot', 'Sorry, there was an error processing your request. Please try again.');
    return;
  }

  // Remember session for subsequent messages
  state.currentSession = data.session_id;
  appendMessage('bot', data.response);
  loadChatHistory(); // Refresh history sidebar
}

function sendSuggestion(btn) {
  document.getElementById('chat-input').value = btn.textContent;
  sendMessage();
}

function appendMessage(role, text) {
  const chatWin = document.getElementById('chat-window');
  const msg     = document.createElement('div');
  msg.className = `chat-msg ${role === 'user' ? 'user-msg' : ''}`;

  const user     = state.currentUser;
  const initials = user ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : 'U';
  const now      = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  const formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');

  if (role === 'bot') {
    msg.innerHTML = `
      <div class="msg-avatar bot-avatar">◈</div>
      <div>
        <div class="msg-bubble bot-bubble">${formatted}</div>
        <div class="msg-time">${now}</div>
      </div>`;
  } else {
    msg.innerHTML = `
      <div class="msg-avatar user-avatar">${initials}</div>
      <div>
        <div class="msg-bubble user-bubble">${escHtml(text)}</div>
        <div class="msg-time">${now}</div>
      </div>`;
  }

  chatWin.appendChild(msg);
  chatWin.scrollTop = chatWin.scrollHeight;
}

function showTyping() {
  const chatWin = document.getElementById('chat-window');
  const div     = document.createElement('div');
  div.className = 'chat-msg'; div.id = 'typing-bubble';
  div.innerHTML = `
    <div class="msg-avatar bot-avatar">◈</div>
    <div class="msg-bubble bot-bubble">
      <div class="typing-indicator">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
      </div>
    </div>`;
  chatWin.appendChild(div);
  chatWin.scrollTop = chatWin.scrollHeight;
}

function hideTyping() {
  const t = document.getElementById('typing-bubble');
  if (t) t.remove();
}

function clearChat() {
  resetChatWelcome();
  showToast('New chat started.');
}

function chatKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ─── CHAT HISTORY (sidebar list) ─────────────────────────
async function loadChatHistory() {
  const list = document.getElementById('history-list');
  const data = await api('chat.php?action=history');

  if (!data.success || !data.sessions?.length) {
    list.innerHTML = `
      <div class="history-empty">
        <div class="he-icon">🕓</div>
        <p>Your chat history will appear here after your first conversation.</p>
      </div>`;
    return;
  }

  list.innerHTML = data.sessions.map(s => `
    <div class="history-card" onclick="loadSession(${s.id})" style="cursor:pointer">
      <div class="hc-meta">
        <span class="hc-date">${new Date(s.last_message || s.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
        <span style="font-size:11px;color:var(--text-mute)">${s.message_count} msgs</span>
      </div>
      <div class="hc-q">${escHtml(s.title)}</div>
    </div>`).join('');
}

async function loadSession(sessionId) {
  const data = await api(`chat.php?action=session&id=${sessionId}`);
  if (!data.success) return showToast('Failed to load session.');

  state.currentSession = sessionId;
  const chatWin = document.getElementById('chat-window');
  chatWin.innerHTML = '';

  data.messages.forEach(m => appendMessage(m.role === 'user' ? 'user' : 'bot', m.content));
  showPage('student-dashboard');
  switchTab(document.querySelector('#student-dashboard .sb-link'), 'chat-tab', 'student-dashboard');
}

function filterHistory(query) {
  document.querySelectorAll('.history-card').forEach(card => {
    card.style.display = card.textContent.toLowerCase().includes(query.toLowerCase()) ? '' : 'none';
  });
}

// ─── FILE UPLOAD ──────────────────────────────────────────
let currentFile = null;

function dropFile(e) {
  e.preventDefault();
  const file = e.dataTransfer.files[0];
  if (file) processFile(file);
  document.getElementById('upload-zone').classList.remove('dragover');
}
function dragOver(e) {
  e.preventDefault();
  document.getElementById('upload-zone').classList.add('dragover');
}
function dragLeave() {
  document.getElementById('upload-zone').classList.remove('dragover');
}
function handleFileUpload(input) {
  if (input.files[0]) processFile(input.files[0]);
}

function processFile(file) {
  if (!file.name.match(/\.(pdf|txt|doc|docx)$/i)) {
    return showToast('Only PDF, TXT, DOC, DOCX files are supported.');
  }
  currentFile = file;
  document.getElementById('fp-name').textContent = file.name;
  document.getElementById('fp-size').textContent = formatFileSize(file.size);
  document.getElementById('file-preview').classList.remove('hidden');
  document.getElementById('summary-output').classList.add('hidden');
}

function removeFile() {
  currentFile = null;
  document.getElementById('file-preview').classList.add('hidden');
  document.getElementById('summary-output').classList.add('hidden');
  document.getElementById('file-upload').value = '';
}

async function summarizeFile() {
  if (!currentFile) return;
  const soBody     = document.getElementById('so-body');
  const summaryOut = document.getElementById('summary-output');
  summaryOut.classList.remove('hidden');
  soBody.innerHTML = '<div style="display:flex;gap:6px;align-items:center;color:var(--text-mute)"><div style="width:14px;height:14px;border:2px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite"></div> Uploading and analyzing your notes...</div>';

  const formData = new FormData();
  formData.append('file', currentFile);
  if (state.currentSession) formData.append('session_id', state.currentSession);

  const data = await apiUpload('upload.php', formData);

  if (!data.success) {
    soBody.innerHTML = `<span style="color:var(--error)">${data.message}</span>`;
    return;
  }

  const summary = data.file.summary || 'File uploaded successfully. Text extraction is available for TXT files. For PDF content, ensure a PDF parser is installed.';
  soBody.innerHTML = summary.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

  await loadUploadedFiles();
  showToast('File uploaded and summarized successfully!');
}

function handleChatFileAttach(input) {
  if (input.files[0]) showToast(`Attached: ${input.files[0].name}`);
}

function addToUploadedList(file) {
  const list    = document.getElementById('uploaded-list');
  const emptyEl = list.querySelector('.ul-empty');
  if (emptyEl) emptyEl.remove();

  const item = document.createElement('div');
  item.className = 'ul-item';
  item.innerHTML = `
    <span>📄</span>
    <span class="ul-item-name">${escHtml(file.name)}</span>
    <span class="ul-item-date">${new Date().toLocaleDateString()}</span>`;
  list.appendChild(item);
}

async function loadUploadedFiles() {
  const list = document.getElementById('uploaded-list');
  const data = await api('upload.php');

  if (!data.success || !data.files) {
    list.innerHTML = `
      <h4>Recent Uploads</h4>
      <div class="ul-empty">No files uploaded yet</div>`;
    state.fileCount = 0;
    document.getElementById('stat-files').textContent = state.fileCount;
    return;
  }

  state.fileCount = data.total || data.files.length || 0;
  document.getElementById('stat-files').textContent = state.fileCount;

  list.innerHTML = '<h4>Recent Uploads</h4>' + data.files.map(file => `
    <div class="ul-item">
      <span>📄</span>
      <span class="ul-item-name">${escHtml(file.original_name)}</span>
      <span class="ul-item-date">${new Date(file.created_at).toLocaleDateString()}</span>
      <button class="ul-delete" onclick="deleteUploadedFile(${file.id})">Delete</button>
    </div>
  `).join('');
}

async function deleteUploadedFile(fileId) {
  if (!confirm('Delete this file? This cannot be undone.')) return;
  const data = await api(`upload.php?id=${fileId}`, { method: 'DELETE' });
  if (!data.success) return showToast(data.message || 'Failed to delete file.');
  await loadUploadedFiles();
  showToast('File deleted successfully.');
}

function copySummary() {
  const text = document.getElementById('so-body').textContent;
  navigator.clipboard.writeText(text).then(() => showToast('Summary copied to clipboard!'));
}

function formatFileSize(bytes) {
  if (bytes < 1024)    return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

// ─── PROFILE ──────────────────────────────────────────────
function saveProfile() {
  const name = document.getElementById('pf-name').value.trim();
  if (!name) return showToast('Name cannot be empty.');
  state.currentUser.name = name;
  document.getElementById('profile-name').textContent  = name;
  document.getElementById('sb-user-name').textContent  = name;
  const initials = name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  document.getElementById('profile-avatar').textContent = initials;
  showToast('Profile updated successfully!');
}

// ─── ADMIN: STATS ─────────────────────────────────────────
async function loadAdminStats() {
  const data = await api('admin.php?action=stats');
  if (!data.success) return;
  const s = data.stats;
  document.getElementById('asg-users').textContent = s.total_users;
  document.getElementById('asg-chats').textContent = s.total_messages;
  document.getElementById('asg-kb').textContent    = s.total_kb;
  document.getElementById('asg-files').textContent = s.total_files;
}

// ─── ADMIN: USER MANAGEMENT ───────────────────────────────
async function loadUsersTable() {
  const data = await api('admin.php?action=users');
  if (!data.success) return;

  const tbody = document.getElementById('users-tbody');
  tbody.innerHTML = data.users.map(u => `
    <tr data-id="${u.id}">
      <td>${escHtml(u.name)}</td>
      <td>${escHtml(u.email)}</td>
      <td><span class="badge ${u.status === 'active' ? 'active' : 'inactive'}">${u.status === 'active' ? 'Active' : 'Inactive'}</span></td>
      <td>${new Date(u.created_at).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' })}</td>
      <td>
        <button class="tbl-btn" onclick="toggleUserStatus(this, ${u.id})">${u.status === 'active' ? 'Deactivate' : 'Activate'}</button>
        <button class="tbl-btn danger" onclick="deleteUser(this, ${u.id})">Delete</button>
      </td>
    </tr>`).join('');
}

async function toggleUserStatus(btn, userId) {
  const data = await api(`admin.php?action=toggle_user&id=${userId}`, { method: 'PUT' });
  if (!data.success) return showToast(data.message);

  const row    = btn.closest('tr');
  const badge  = row.querySelector('.badge');
  const active = data.new_status === 'active';
  badge.className    = `badge ${active ? 'active' : 'inactive'}`;
  badge.textContent  = active ? 'Active' : 'Inactive';
  btn.textContent    = active ? 'Deactivate' : 'Activate';
  showToast(`User ${active ? 'activated' : 'deactivated'}.`);
}

async function deleteUser(btn, userId) {
  if (!confirm('Are you sure you want to delete this user?')) return;

  const data = await api(`admin.php?action=delete_user&id=${userId}`, { method: 'DELETE' });
  if (!data.success) return showToast(data.message);

  btn.closest('tr').remove();
  loadAdminStats();
  showToast('User deleted successfully.');
}

function openAddUserModal() {
  document.getElementById('au-name').value  = '';
  document.getElementById('au-email').value = '';
  openModal('adduser-modal');
}

async function addUser() {
  const name  = document.getElementById('au-name').value.trim();
  const email = document.getElementById('au-email').value.trim();
  if (!name || !email) return showToast('Please fill in all fields.');

  const data = await api('admin.php?action=add_user', {
    method: 'POST',
    body: JSON.stringify({ name, email }),
  });

  if (!data.success) return showToast(data.message);

  closeModal();
  loadUsersTable();
  loadAdminStats();
  showToast(data.message);
}

// ─── ADMIN: KNOWLEDGE BASE ────────────────────────────────
let editingKBId = null;

async function loadKBEntries() {
  const data = await api('knowledge.php');
  if (!data.success) return;

  const grid = document.getElementById('kb-grid');
  grid.innerHTML = data.entries.map(e => `
    <div class="kb-card" data-id="${e.id}">
      <div class="kb-cat">${escHtml(e.category)}</div>
      <h4>${escHtml(e.title)}</h4>
      <p>${escHtml(e.content.slice(0, 150))}${e.content.length > 150 ? '...' : ''}</p>
      <div class="kb-actions">
        <button onclick="editKB(this)">Edit</button>
        <button class="danger" onclick="deleteKB(this)">Delete</button>
      </div>
    </div>`).join('');
}

function openKBModal() {
  editingKBId = null;
  document.getElementById('kb-modal-title').textContent = 'Add Knowledge Entry';
  document.getElementById('kb-title').value   = '';
  document.getElementById('kb-cat').value     = '';
  document.getElementById('kb-content').value = '';
  openModal('kb-modal');
}

function editKB(btn) {
  const card    = btn.closest('.kb-card');
  editingKBId   = parseInt(card.dataset.id);
  document.getElementById('kb-modal-title').textContent = 'Edit Knowledge Entry';
  document.getElementById('kb-title').value   = card.querySelector('h4').textContent;
  document.getElementById('kb-cat').value     = card.querySelector('.kb-cat').textContent;
  // Load full content via API
  api(`knowledge.php?id=${editingKBId}`).then(data => {
    // Just use the card paragraph for now
  });
  document.getElementById('kb-content').value = card.querySelector('p').textContent.replace(/\.\.\.$/, '');
  openModal('kb-modal');
}

async function saveKBEntry() {
  const title   = document.getElementById('kb-title').value.trim();
  const cat     = document.getElementById('kb-cat').value.trim();
  const content = document.getElementById('kb-content').value.trim();
  if (!title || !cat || !content) return showToast('Please fill in all fields.');

  let data;
  if (editingKBId) {
    data = await api(`knowledge.php?action=edit&id=${editingKBId}`, {
      method: 'PUT',
      body: JSON.stringify({ title, category: cat, content }),
    });
  } else {
    data = await api('knowledge.php?action=add', {
      method: 'POST',
      body: JSON.stringify({ title, category: cat, content }),
    });
  }

  if (!data.success) return showToast(data.message);

  closeModal();
  loadKBEntries();
  loadAdminStats();
  showToast(editingKBId ? 'Entry updated.' : 'Entry added.');
}

async function deleteKB(btn) {
  if (!confirm('Delete this knowledge entry?')) return;
  const id   = parseInt(btn.closest('.kb-card').dataset.id);
  const data = await api(`knowledge.php?action=delete&id=${id}`, { method: 'DELETE' });
  if (!data.success) return showToast(data.message);

  btn.closest('.kb-card').remove();
  loadAdminStats();
  showToast('Entry deleted.');
}

// ─── ADMIN: CHAT LOGS ─────────────────────────────────────
async function loadChatLogs(search = '', dateRange = 'all') {
  const data = await api(`admin.php?action=logs&search=${encodeURIComponent(search)}&date_range=${dateRange}`);
  if (!data.success) return;

  const list = document.getElementById('logs-list');

  // Group logs by session (user messages paired with bot responses)
  const userMsgs = data.logs.filter(l => l.role === 'user');

  if (!userMsgs.length) {
    list.innerHTML = '<p style="color:var(--text-mute);padding:20px">No chat logs found.</p>';
    return;
  }

  list.innerHTML = userMsgs.map(m => {
    // Find the corresponding bot response (next message in logs from same session)
    const botMsg = data.logs.find(l => l.role === 'bot' && l.session_title === m.session_title);
    return `
      <div class="log-item">
        <div class="log-meta">
          <strong>${escHtml(m.student_name)}</strong>
          <span>${new Date(m.created_at).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
        </div>
        <div class="log-q">Q: ${escHtml(m.content)}</div>
        ${botMsg ? `<div class="log-a">A: ${escHtml(botMsg.content.slice(0, 200))}${botMsg.content.length > 200 ? '...' : ''}</div>` : ''}
      </div>`;
  }).join('');
}

function filterLogs(query) {
  loadChatLogs(query);
}

function filterLogsByDate(value) {
  const map = { 'Today': 'today', 'This Week': 'week', 'All Time': 'all' };
  loadChatLogs('', map[value] || 'all');
}

// ─── MODALS ───────────────────────────────────────────────
function openModal(id) {
  document.getElementById('modal-overlay').classList.add('show');
  document.getElementById(id).classList.add('show');
}
function closeModal() {
  document.getElementById('modal-overlay').classList.remove('show');
  document.querySelectorAll('.modal').forEach(m => m.classList.remove('show'));
}

// ─── TOAST ────────────────────────────────────────────────
let toastTimer = null;
function showToast(msg) {
  const toast    = document.getElementById('toast');
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}

// ─── HELPERS ──────────────────────────────────────────────
function showMsg(el, type, msg) {
  el.textContent = msg;
  el.className   = `form-msg ${type}`;
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,  '&amp;')
    .replace(/</g,  '&lt;')
    .replace(/>/g,  '&gt;')
    .replace(/"/g,  '&quot;');
}

// ─── KEYBOARD SHORTCUTS ───────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModal();
});

// ─── AUTO LOGIN (restore session from localStorage) ───────
async function tryAutoLogin() {
  const savedToken = localStorage.getItem('aac_token');
  if (!savedToken) return;

  state.token = savedToken;
  const data  = await api('auth.php?action=me');

  if (data.success) {
    state.currentUser = data.user;
    loginUser(data.user);
  } else {
    localStorage.removeItem('aac_token');
    state.token = null;
  }
}

// ─── INIT ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  showPage('landing');
  initCanvas();

  document.getElementById('login-pass').addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
  });
  document.getElementById('reg-cpass').addEventListener('keydown', e => {
    if (e.key === 'Enter') doRegister();
  });

  // Try to restore session
  tryAutoLogin();
});

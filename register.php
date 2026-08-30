<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Registration - SCHOLARSYNC GLOBAL</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
--primary: #427431;
--primary-dark: #2f5a26;
--primary-light: #3661B9;
--accent: #E21D1E;
--accent-dark: #e6732f;
--accent-light: #ffa366;
--bg: #f8fafc;
--bg-light: #ffffff;
--card: #ffffff;
--text: #1e293b;
--text-light: #64748b;
--text-muted: #94a3b8;
--muted: #64748b;
--danger: #dc2626;
--danger-light: #fee2e2;
--success: #10b981;
--border: #e2e8f0;
--border-focus: #3b82f6;
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
--shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
--transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
--transition-fast: all 0.15s ease;
}
*, *::before, *::after {
box-sizing: border-box;
margin: 0;
padding: 0;
}
body {
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
margin: 0;
min-height: 100vh;
background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 50%, #f8fafc 100%);
background-image: 
radial-gradient(circle at 20% 30%, rgba(30, 58, 95, 0.08) 0%, transparent 50%),
radial-gradient(circle at 80% 70%, rgba(226, 29, 30, 0.1) 0%, transparent 50%),
radial-gradient(circle at 50% 50%, rgba(30, 58, 95, 0.04) 0%, transparent 70%);
display: flex;
align-items: center;
justify-content: center;
padding: 20px;
position: relative;
overflow-x: hidden;
}
body::before {
content: '';
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
background-image: 
repeating-linear-gradient(
45deg,
transparent,
transparent 100px,
rgba(30, 58, 95, 0.02) 100px,
rgba(30, 58, 95, 0.02) 102px
);
pointer-events: none;
z-index: 0;
}
.container {
max-width: 480px;
width: 100%;
position: relative;
z-index: 10;
background: var(--card);
border-radius: 20px;
padding: 48px 40px;
box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
border: 1px solid rgba(226, 232, 240, 0.8);
overflow: hidden;
backdrop-filter: blur(10px);
animation: fadeInUp 0.6s ease-out;
}
@keyframes fadeInUp {
from {
opacity: 0;
transform: translateY(20px);
}
to {
opacity: 1;
transform: translateY(0);
}
}
.logo-container {
text-align: center;
margin-bottom: 40px;
}
.logo-container img {
height: 120px;
width: auto;
margin-bottom: 20px;
transition: var(--transition);
filter: drop-shadow(0 4px 12px rgba(30, 58, 95, 0.2));
}
.logo-container img:hover {
transform: translateY(-3px) scale(1.02);
filter: drop-shadow(0 6px 16px rgba(30, 58, 95, 0.25));
}
.header {
text-align: center;
margin-bottom: 32px;
}
.header h2 {
font-size: 1.875rem;
font-weight: 700;
letter-spacing: -0.025em;
color: var(--text);
}
.form-group {
position: relative;
margin-bottom: 20px;
}
.form-group label {
display: block;
margin-bottom: 8px;
color: var(--text);
font-size: 0.875rem;
font-weight: 500;
}
.input-wrapper {
position: relative;
display: flex;
align-items: center;
}
.input-wrapper i {
position: absolute;
left: 16px;
color: var(--muted);
font-size: 1rem;
z-index: 1;
transition: var(--transition);
}
input[type="text"],
input[type="email"],
input[type="password"] {
width: 100%;
padding: 14px 16px 14px 44px;
border-radius: 12px;
border: 2px solid var(--border);
font-size: 0.95rem;
transition: var(--transition);
background: #fff;
color: var(--text);
outline: none;
}
input::placeholder {
color: var(--muted);
}
input:focus {
border-color: var(--primary);
box-shadow: 0 0 0 4px rgba(30, 58, 95, 0.1);
}
input:focus + i,
.input-wrapper:has(input:focus) i {
color: var(--primary);
}
.error {
color: var(--danger);
font-size: 0.9rem;
margin-top: 6px;
display: flex;
align-items: center;
gap: 8px;
animation: shake 0.3s ease-in-out;
}
@keyframes shake {
0%, 100% { transform: translateX(0); }
25% { transform: translateX(-8px); }
75% { transform: translateX(8px); }
}
.success {
color: var(--success);
font-size: 0.9rem;
margin-top: 6px;
display: flex;
align-items: center;
gap: 8px;
}
.btn {
width: 100%;
padding: 16px;
border: none;
border-radius: 12px;
background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
color: #fff;
font-weight: 600;
font-size: 1rem;
cursor: pointer;
transition: var(--transition);
position: relative;
overflow: hidden;
margin-top: 32px;
box-shadow: 0 4px 12px rgba(30, 58, 95, 0.25);
}
.btn::before {
content: '';
position: absolute;
top: 0;
left: -100%;
width: 100%;
height: 100%;
background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
transition: left 0.5s;
}
.btn:hover::before {
left: 100%;
}
.btn:hover {
transform: translateY(-2px);
box-shadow: 0 8px 24px rgba(30, 58, 95, 0.35);
background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
}
.btn:active {
transform: translateY(0);
box-shadow: 0 4px 12px rgba(30, 58, 95, 0.25);
}
.btn:disabled {
opacity: 0.6;
cursor: not-allowed;
transform: none;
background: var(--muted);
}
.loading {
width: 20px;
height: 20px;
border: 3px solid rgba(255, 255, 255, 0.3);
border-top-color: #fff;
border-radius: 50%;
animation: spin 0.8s linear infinite;
margin: 0 auto;
display: none;
}
@keyframes spin {
to { transform: translate(-50%, -50%) rotate(360deg); }
}
.btn.loading-state .btn-text {
display: none;
}
.btn.loading-state .loading {
display: block;
}
.password-strength {
margin-top: 8px;
height: 4px;
background: #e2e8f0;
border-radius: 2px;
overflow: hidden;
}
.password-strength-bar {
height: 100%;
width: 0;
transition: all 0.3s ease;
border-radius: 2px;
}
.strength-weak { background: #f56565; width: 33%; }
.strength-medium { background: #ed8936; width: 66%; }
.strength-strong { background: #10b981; width: 100%; }
.back-link {
text-align: center;
margin-top: 24px;
}
.back-link a {
color: var(--primary);
text-decoration: none;
font-size: 0.875rem;
font-weight: 600;
transition: color 0.3s ease;
}
.back-link a:hover {
color: var(--primary-light);
text-decoration: underline;
}
@media (max-width: 480px) {
.container {
padding: 36px 28px;
border-radius: 16px;
margin: 10px;
}
.header h2 {
font-size: 1.5rem;
margin-bottom: 28px;
}
input[type="text"],
input[type="email"],
input[type="password"] {
padding: 12px 14px 12px 44px;
}
.btn {
padding: 14px;
}
}
</style>
</head>
<body>
<div class="container">
  <div class="logo-container">
    <img src="scholarsync-global-logo.jpg" alt="SCHOLARSYNC GLOBAL" onerror="this.style.display='none'">
  </div>
  <div class="header">
    <h2>Register Your Account </h2>
  </div>
  <form method="POST" action="register_staff.php" id="staffForm">
    <div class="form-group">
      <label for="first_name">First Name</label>
      <div class="input-wrapper">
        <input type="text" name="first_name" id="first_name" required placeholder="Enter your first name">
        <i class="fas fa-user"></i>
      </div>
      <div id="firstNameError" class="error"></div>
    </div>
    <div class="form-group">
      <label for="last_name">Last Name</label>
      <div class="input-wrapper">
        <input type="text" name="last_name" id="last_name" required placeholder="Enter your last name">
        <i class="fas fa-user"></i>
      </div>
    </div>
    <div class="form-group">
      <label for="username">Username</label>
      <div class="input-wrapper">
        <input type="text" name="username" id="username" required placeholder="Choose a username">
        <i class="fas fa-at"></i>
      </div>
    </div>
    <div class="form-group">
      <label for="phone_number">Phone Number</label>
      <div class="input-wrapper">
        <input type="text" name="phone_number" id="phone_number" required placeholder="Enter your phone number">
        <i class="fas fa-phone"></i>
      </div>
    </div>
    <div class="form-group">
      <label for="email">Email Address</label>
      <div class="input-wrapper">
        <input type="email" name="email" id="email" required placeholder="Enter your email">
        <i class="fas fa-envelope"></i>
      </div>
      <div id="emailError" class="error"></div>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <div class="input-wrapper">
        <input type="password" name="password" id="password" required placeholder="Create a strong password">
        <i class="fas fa-lock"></i>
      </div>
      <div class="password-strength">
        <div class="password-strength-bar" id="passwordStrength"></div>
      </div>
    </div>
    <input type="hidden" name="role" value="agent">
    <button type="submit" id="submitBtn" class="btn">
      <span class="btn-text">Create Account</span>
      <div class="loading"></div>
    </button>
  </form>
  <div class="back-link">
    <a href="admin-login.php">Already have an account? Sign in</a>
  </div>
</div>
<script>
// Original JavaScript preserved without change as requested
const firstNameInput = document.getElementById('first_name');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const firstNameError = document.getElementById('firstNameError');
const emailError = document.getElementById('emailError');
const submitBtn = document.getElementById('submitBtn');
const passwordStrength = document.getElementById('passwordStrength');
let firstNameTimeout, emailTimeout;
firstNameInput.addEventListener('input', () => {
  clearTimeout(firstNameTimeout);
  firstNameTimeout = setTimeout(checkFirstName, 500);
});
emailInput.addEventListener('input', () => {
  clearTimeout(emailTimeout);
  emailTimeout = setTimeout(checkEmail, 500);
});
passwordInput.addEventListener('input', checkPasswordStrength);
function checkFirstName() {
  const first_name = firstNameInput.value.trim();
  if (first_name.length < 2) {
    firstNameError.innerHTML = '';
    submitBtn.disabled = false;
    return;
  }
  firstNameError.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
  fetch('check_user.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'type=first_name&value=' + encodeURIComponent(first_name)
  })
  .then(response => response.text())
  .then(data => {
    if (data === 'exists') {
      firstNameError.innerHTML = '<i class="fas fa-exclamation-circle"></i> First name already exists!';
      submitBtn.disabled = true;
    } else {
      firstNameError.innerHTML = '<i class="fas fa-check-circle"></i> Available';
      firstNameError.className = 'success';
      submitBtn.disabled = false;
    }
  })
  .catch(() => {
    firstNameError.innerHTML = '';
    submitBtn.disabled = false;
  });
}
function checkEmail() {
  const email = emailInput.value.trim();
  if (email.length < 5) {
    emailError.innerHTML = '';
    submitBtn.disabled = false;
    return;
  }
  emailError.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
  fetch('check_user.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'type=email&value=' + encodeURIComponent(email)
  })
  .then(response => response.text())
  .then(data => {
    if (data === 'exists') {
      emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Email already exists!';
      emailError.className = 'error';
      submitBtn.disabled = true;
    } else {
      emailError.innerHTML = '<i class="fas fa-check-circle"></i> Available';
      emailError.className = 'success';
      submitBtn.disabled = false;
    }
  })
  .catch(() => {
    emailError.innerHTML = '';
    submitBtn.disabled = false;
  });
}
function checkPasswordStrength() {
  const password = passwordInput.value;
  let strength = 0;
  if (password.length >= 8) strength++;
  if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
  if (password.match(/[0-9]/)) strength++;
  if (password.match(/[^a-zA-Z0-9]/)) strength++;
  passwordStrength.className = 'password-strength-bar';
  if (password.length === 0) {
    passwordStrength.style.width = '0';
  } else if (strength <= 1) {
    passwordStrength.classList.add('strength-weak');
  } else if (strength === 2) {
    passwordStrength.classList.add('strength-medium');
  } else {
    passwordStrength.classList.add('strength-strong');
  }
}
document.getElementById('staffForm').addEventListener('submit', function(e) {
  submitBtn.classList.add('loading-state');
  submitBtn.disabled = true;
});
</script>
</body>
</html>
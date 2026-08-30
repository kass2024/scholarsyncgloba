/**
 * Copy @zoom/meetingsdk + React/Redux UMD vendor files for PHP host page.
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const pkgDir = path.join(root, 'node_modules', '@zoom', 'meetingsdk');
const outDir = path.join(root, 'assets', 'zoom-meetingsdk');

function copyRecursive(src, dest) {
  if (!fs.existsSync(src)) return;
  const stat = fs.statSync(src);
  if (stat.isDirectory()) {
    fs.mkdirSync(dest, { recursive: true });
    for (const entry of fs.readdirSync(src)) {
      copyRecursive(path.join(src, entry), path.join(dest, entry));
    }
    return;
  }
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.copyFileSync(src, dest);
}

function copyFile(src, dest) {
  if (!fs.existsSync(src)) {
    console.warn('[copy-zoom-sdk] missing:', src);
    return;
  }
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.copyFileSync(src, dest);
}

if (!fs.existsSync(pkgDir)) {
  console.warn('[copy-zoom-sdk] @zoom/meetingsdk not installed — run npm install');
  process.exit(0);
}

fs.mkdirSync(outDir, { recursive: true });

const distDir = path.join(pkgDir, 'dist');
if (fs.existsSync(distDir)) {
  copyRecursive(distDir, path.join(outDir, 'dist'));
}

const vendorOut = path.join(outDir, 'vendor');
copyFile(path.join(root, 'node_modules', 'react', 'umd', 'react.production.min.js'), path.join(vendorOut, 'react.min.js'));
copyFile(path.join(root, 'node_modules', 'react-dom', 'umd', 'react-dom.production.min.js'), path.join(vendorOut, 'react-dom.min.js'));
copyFile(path.join(root, 'node_modules', 'redux', 'dist', 'redux.min.js'), path.join(vendorOut, 'redux.min.js'));
copyFile(path.join(root, 'node_modules', 'redux-thunk', 'dist', 'redux-thunk.min.js'), path.join(vendorOut, 'redux-thunk.min.js'));

const meetingBundles = fs.readdirSync(distDir).filter((f) => /^zoom-meeting-[\d.]+\.min\.js$/.test(f));
const meetingJs = meetingBundles.sort().pop() || 'zoom-meeting-6.2.0.min.js';
fs.writeFileSync(path.join(outDir, 'manifest.json'), JSON.stringify({ meetingJs, version: meetingJs.replace(/^zoom-meeting-|\.min\.js$/g, '') }, null, 2));

console.log('[copy-zoom-sdk] Copied Zoom Meeting SDK to assets/zoom-meetingsdk/ (' + meetingJs + ')');

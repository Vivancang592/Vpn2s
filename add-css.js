// Add CSS to app.css at the end
const fs = require('fs');
const cssPath = 'd:/laragon/www/VPN2S/public/assets/css/app.css';
const filePath = 'd:/laragon/www/VPN2S/public/assets/css/app-css-add.txt';

try {
    const content = fs.readFileSync(cssPath, 'utf8');
    const newCSS = `/* Gói được ẩn theo filter group */\n.user-plan-card.is-hidden, .plan-card-vip.is-hidden { display: none !important; }\n`;
    const finalContent = content + newCSS;
    fs.writeFileSync(cssPath, finalContent, 'utf8');
    console.log('CSS đã được thêm vào app.css!');
    process.exit(0);
} catch (e) {
    console.error('Lỗi:', e.message);
    process.exit(1);
}
import './bootstrap';
import Chart from 'chart.js/auto';

window.Chart = Chart;

// Pemindai QR hanya dimuat di halaman yang membutuhkannya
window.loadQrScanner = () => import('html5-qrcode').then(m => m.Html5Qrcode);

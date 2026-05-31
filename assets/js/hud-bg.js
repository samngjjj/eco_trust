/**
 * JARVIS HUD Background Animation - Optimized for Performance
 */
const canvas = document.getElementById('canvas-hud');
if (canvas) {
    const ctx = canvas.getContext('2d');
    let w, h;

    function resize() {
        w = canvas.width = window.innerWidth;
        h = canvas.height = window.innerHeight;
    }

    class Circuit {
        constructor() { this.reset(); }
        reset() {
            this.x = Math.random() * w; this.y = Math.random() * h;
            this.len = Math.random() * 80 + 40;
            this.angle = (Math.floor(Math.random() * 4) * 90) * (Math.PI / 180);
            this.speed = Math.random() * 1.5 + 0.5;
            this.opacity = Math.random() * 0.12; // Dimmed for better contrast/readability
        }
        update() {
            this.x += Math.cos(this.angle) * this.speed;
            this.y += Math.sin(this.angle) * this.speed;
            if (this.x < -100 || this.x > w + 100 || this.y < -100 || this.y > h + 100) this.reset();
        }
        draw() {
            ctx.beginPath(); ctx.lineWidth = 1;
            ctx.moveTo(this.x, this.y);
            ctx.lineTo(this.x + Math.cos(this.angle) * this.len, this.y + Math.sin(this.angle) * this.len);
            ctx.strokeStyle = `rgba(41, 121, 255, ${this.opacity})`;
            ctx.stroke();
        }
    }

    // Reduced line count from 45 to 15 to cut CPU/GPU rendering overhead by 66%
    const circuits = Array.from({ length: 15 }, () => new Circuit());

    function init() { resize(); }

    function animateHUD() {
        ctx.clearRect(0, 0, w, h);
        
        // Update and draw circuits
        circuits.forEach(c => { c.update(); c.draw(); });

        requestAnimationFrame(animateHUD);
    }

    window.addEventListener('resize', init);
    init();
    animateHUD();
}

/**
 * Custom Tech Cursor Trail - Disabled per user request
 */
const outline = document.getElementById('cursor-outline');
if (outline) {
    outline.style.display = 'none';
}

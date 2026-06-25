// frontend/src/app/components/pull/pull.component.ts
// Slot machine gacha — spins three reels and reveals the pulled item.
// The PHP API is called immediately on click — the result is decided
// server-side instantly. The animation plays CLIENT-SIDE only,
// then reveals the real result when the spin completes.

import { Component, Output, EventEmitter, OnDestroy } from '@angular/core';
import { GachaService } from '../../services/gacha.service';

@Component({
  selector: 'app-pull',
  templateUrl: './pull.component.html',
  styleUrls: ['./pull.component.css']
})
export class PullComponent implements OnDestroy {

  // ── State ────────────────────────────────────────────────────
  pulledItem: any   = null;   // final result shown after spin
  pendingResult: any = null;  // result from PHP, held until animation ends
  isLoading         = false;  // true while PHP is responding
  isSpinning        = false;  // true while animation is playing
  isRevealed        = false;  // true once result card is shown
  wasPity5          = false;
  wasPity4          = false;
  error             = '';
  showFlash         = false;  // 5-star screen flash overlay

  // ── Reel state ───────────────────────────────────────────────
  // Three reels, each tracks whether it has stopped yet.
  // reel1 stops first, reel2 second, reel3 last (staggered effect).
  reel1Stopped = false;
  reel2Stopped = false;
  reel3Stopped = false;

  // Symbol pools shown on each reel while spinning.
  // All three show random symbols — the real result replaces reel3
  // when it stops. This is the classic slot machine illusion.
  symbols = ['⚔️', '🏹', '🛡️', '🔮', '💎', '🌟', '🗡️', '🪄'];

  // Current symbol shown on each reel (cycles during spin)
  reel1Symbol = '⚔️';
  reel2Symbol = '🏹';
  reel3Symbol = '🛡️';

  // setTimeout handles so we can cancel them if the component is destroyed
  private timers: any[] = [];

  // Audio context for sound effects
  private audioCtx: AudioContext | null = null;

  @Output() pullComplete = new EventEmitter<any>();

  constructor(private gachaService: GachaService) {}

  // ── Main pull function ────────────────────────────────────────
  pull() {
    if (this.isSpinning || this.isLoading) return;

    this.isLoading   = true;
    this.isRevealed  = false;
    this.error       = '';
    this.reel1Stopped = false;
    this.reel2Stopped = false;
    this.reel3Stopped = false;

    // Call PHP API immediately — result decided server-side
    this.gachaService.pull().subscribe({
      next: (result) => {
        this.pendingResult = result;  // store result, don't show yet
        this.isLoading     = false;
        this.startSpin();            // now start the animation
      },
      error: (err) => {
        this.error     = 'Pull failed — check that the PHP containers are running.';
        this.isLoading = false;
        console.error(err);
      }
    });
  }

  // ── Spin animation ────────────────────────────────────────────
  // Cycles symbols on all three reels rapidly, then stops them
  // one by one with increasing delays (staggered slot machine feel).
  startSpin() {
    this.isSpinning = true;
    this.playSpinSound();

    // Cycle symbols every 80ms to create spinning illusion
    // Each reel gets a slightly different interval for visual variety
    let i = 0;
    const spinInterval = setInterval(() => {
      this.reel1Symbol = this.symbols[i % this.symbols.length];
      this.reel2Symbol = this.symbols[(i + 2) % this.symbols.length];
      this.reel3Symbol = this.symbols[(i + 4) % this.symbols.length];
      i++;
    }, 80);
    this.timers.push(spinInterval);

    // Reel 1 stops after 1.0 second
    const t1 = setTimeout(() => {
      this.reel1Stopped = true;
      this.reel1Symbol  = this.getReelSymbol(1);
      this.playStopSound();
      clearInterval(spinInterval); // stop reel1 cycling
      // Start reel2-only cycling after reel1 stops
      this.spinReels2And3(i);
    }, 1000);
    this.timers.push(t1);
  }

  // After reel1 stops, keep cycling reels 2 and 3
  spinReels2And3(startI: number) {
    let i = startI;
    const spinInterval = setInterval(() => {
      this.reel2Symbol = this.symbols[(i + 2) % this.symbols.length];
      this.reel3Symbol = this.symbols[(i + 4) % this.symbols.length];
      i++;
    }, 80);
    this.timers.push(spinInterval);

    // Reel 2 stops after 0.8s more (1.8s total)
    const t2 = setTimeout(() => {
      this.reel2Stopped = true;
      this.reel2Symbol  = this.getReelSymbol(2);
      this.playStopSound();
      clearInterval(spinInterval);
      this.spinReel3Only(i);
    }, 800);
    this.timers.push(t2);
  }

  // After reel2 stops, keep cycling reel 3 only
  spinReel3Only(startI: number) {
    let i = startI;
    const spinInterval = setInterval(() => {
      this.reel3Symbol = this.symbols[(i + 4) % this.symbols.length];
      i++;
    }, 80);
    this.timers.push(spinInterval);

    // Reel 3 stops after 0.8s more (2.6s total) — reveals the real result
    const t3 = setTimeout(() => {
      this.reel3Stopped = true;
      this.reel3Symbol  = this.getRaritySymbol(); // show rarity icon
      clearInterval(spinInterval);
      this.playStopSound();
      this.revealResult();
    }, 800);
    this.timers.push(t3);
  }

  // ── Reveal result ─────────────────────────────────────────────
  // Called after all reels stop. Shows the result card.
  revealResult() {
    const result    = this.pendingResult;
    this.pulledItem = result.item;
    this.wasPity5   = result.was_pity_5;
    this.wasPity4   = result.was_pity_4;
    this.isSpinning = false;
    this.isRevealed = true;

    // 5-star flash effect
    if (result.item.rarity === 5) {
      this.triggerFlash();
      this.playFiveStarSound();
    } else {
      this.playRevealSound();
    }

    // Emit result up to AppComponent to update pity bars and history
    this.pullComplete.emit(result);
  }

  // ── Reel symbol helpers ───────────────────────────────────────
  // Returns a themed symbol for each reel position when it stops.
  // Reels 1 and 2 show random symbols; reel 3 shows the rarity result.
  getReelSymbol(reelNum: number): string {
    const idx = Math.floor(Math.random() * this.symbols.length);
    return this.symbols[idx];
  }

  getRaritySymbol(): string {
    if (!this.pendingResult) return '❓';
    const rarity = this.pendingResult.item.rarity;
    if (rarity === 5) return '💎';
    if (rarity === 4) return '🌟';
    return '⚔️';
  }

  // ── 5-star flash effect ───────────────────────────────────────
  // Shows a gold flash overlay that fades out quickly.
  triggerFlash() {
    this.showFlash = true;
    const t = setTimeout(() => { this.showFlash = false; }, 800);
    this.timers.push(t);
  }

  // ── Sound effects using Web Audio API ────────────────────────
  // Web Audio API generates sounds programmatically — no audio files needed.
  // Each sound is a simple oscillator (tone generator) with a short duration.

  getAudioContext(): AudioContext {
    if (!this.audioCtx) {
      // AudioContext is the browser's sound engine.
      // We create it lazily (on first use) because browsers require
      // a user gesture before allowing audio — creating it on click is safe.
      this.audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
    }
    return this.audioCtx;
  }

  playTone(frequency: number, duration: number, type: OscillatorType = 'sine', volume = 0.3) {
    try {
      const ctx = this.getAudioContext();

      // OscillatorNode generates the tone at the given frequency (Hz)
      const oscillator = ctx.createOscillator();

      // GainNode controls volume — lets us fade out smoothly
      const gainNode = ctx.createGain();

      // Connect: oscillator → gain → speakers
      oscillator.connect(gainNode);
      gainNode.connect(ctx.destination);

      oscillator.type      = type;       // wave shape: sine, square, sawtooth, triangle
      oscillator.frequency.value = frequency;
      gainNode.gain.value  = volume;

      // Fade out at the end so there's no harsh click when the tone stops
      gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);

      oscillator.start(ctx.currentTime);
      oscillator.stop(ctx.currentTime + duration);
    } catch (e) {
      // Silently ignore audio errors — some browsers block audio
    }
  }

  playSpinSound() {
    // Fast ascending sweep — classic slot machine start
    this.playTone(200, 0.1, 'sawtooth', 0.15);
  }

  playStopSound() {
    // Short click — satisfying mechanical stop sound
    this.playTone(400, 0.08, 'square', 0.2);
    setTimeout(() => this.playTone(300, 0.06, 'square', 0.15), 60);
  }

  playRevealSound() {
    // Two-tone chime for normal pulls
    this.playTone(523, 0.15, 'sine', 0.3);  // C5
    setTimeout(() => this.playTone(659, 0.2, 'sine', 0.3), 100); // E5
  }

  playFiveStarSound() {
    // Rising arpeggio for 5-star — feels like a jackpot
    this.playTone(523, 0.15, 'sine', 0.4);  // C5
    setTimeout(() => this.playTone(659, 0.15, 'sine', 0.4), 100); // E5
    setTimeout(() => this.playTone(784, 0.15, 'sine', 0.4), 200); // G5
    setTimeout(() => this.playTone(1047, 0.3, 'sine', 0.4), 300); // C6
  }

  // ── Helpers ───────────────────────────────────────────────────
  getRarityClass() {
    if (!this.pulledItem) return '';
    return `rarity-${this.pulledItem.rarity}`;
  }

  getStars(rarity: number) {
    return '⭐'.repeat(rarity);
  }

  // ── Cleanup ───────────────────────────────────────────────────
  // OnDestroy runs when the component is removed from the page.
  // We clear all timers and close the audio context to prevent memory leaks.
  ngOnDestroy() {
    this.timers.forEach(t => {
      if (typeof t === 'number') clearTimeout(t);
      else clearInterval(t);
    });
    if (this.audioCtx) {
      this.audioCtx.close();
    }
  }
}
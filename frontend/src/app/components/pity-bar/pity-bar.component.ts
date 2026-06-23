// frontend/src/app/components/pity-bar/pity-bar.component.ts
// Displays the 5-star and 4-star pity progress bars.
// Receives pityData from AppComponent via @Input() —
// it doesn't fetch data itself, it just displays what it's given.

import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-pity-bar',
  templateUrl: './pity-bar.component.html',
  styleUrls: ['./pity-bar.component.css']
})
export class PityBarComponent {

  // @Input() means AppComponent passes data into this component:
  //   <app-pity-bar [pityData]="pityData"></app-pity-bar>
  // When AppComponent's pityData changes (after a pull),
  // Angular automatically re-renders this component with the new value.
  @Input() pityData: any = null;

  // ── Computed properties for the 5-star bar ───────────────────

  // Width of the 5-star bar as a percentage (0–100)
  // The ?. is "optional chaining" — safely returns 0 if pityData is null
  get fiveStarPercent(): number {
    return Math.min(((this.pityData?.count_5star ?? 0) / 100) * 100, 100);
  }

  // CSS class for bar colour: green → orange → red
  get fiveStarBarClass(): string {
    if (this.pityData?.in_hard_pity) return 'pity-hard';
    if (this.pityData?.in_soft_pity) return 'pity-soft';
    return 'pity-normal';
  }

  // Label shown below the 5-star bar
  get fiveStarLabel(): string {
    if (this.pityData?.in_hard_pity) {
      return '⚠️ HARD PITY — next pull is guaranteed 5-star!';
    }
    if (this.pityData?.in_soft_pity) {
      return `🔥 Rate climbing — currently at ${this.pityData?.current_rate}%`;
    }
    return `Current rate: ${this.pityData?.current_rate ?? 0.1}% · Rate climbs every 10 pulls · Hard pity at pull 100`;
  }

  // ── Computed properties for the 4-star bar ───────────────────

  // Width of the 4-star bar as a percentage (0–100), resets every 10 pulls
  get fourStarPercent(): number {
    return Math.min(((this.pityData?.count_4star ?? 0) / 10) * 100, 100);
  }

  // Label shown below the 4-star bar
  get fourStarLabel(): string {
    if (this.pityData?.in_4star_pity) {
      return '💜 4-star guaranteed this pull!';
    }
    return `Pull ${this.pityData?.count_4star ?? 0} / 10 — 4-star guaranteed every 10 pulls`;
  }
}

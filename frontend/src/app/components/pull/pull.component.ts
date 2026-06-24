// frontend/src/app/components/pull/pull.component.ts
// Handles the pull button, calls the API, and shows the result card.
// When a pull completes it emits an event UP to AppComponent
// so pity bars and history can update.

import { Component, Output, EventEmitter } from '@angular/core';
import { GachaService } from '../../services/gacha.service';

@Component({
  selector: 'app-pull',
  templateUrl: './pull.component.html',
  styleUrls: ['./pull.component.css']
})
export class PullComponent {

  // Local state — only this component needs to know about these
  pulledItem: any = null;   // the item returned by the last pull
  isLoading = false;        // true while waiting for PHP to respond
  wasPity5  = false;        // did 5-star hard pity trigger?
  wasPity4  = false;        // did 4-star pity trigger?
  error     = '';           // error message if pull fails

  // @Output() + EventEmitter lets this component send data UP to its parent.
  // When pull() succeeds, we emit the result so AppComponent can
  // update the pity bars and history without PullComponent knowing about them.
  // Think of it like a PHP function returning a value to its caller.
  @Output() pullComplete = new EventEmitter<any>();

  constructor(private gachaService: GachaService) {}

  // Called when the user clicks the "Pull x1" button
  pull() {
    this.isLoading = true;
    // Don't clear pulledItem here — keep the previous result visible
    // while waiting for the new pull to come back from PHP.
    // It gets replaced naturally when the new result arrives in next().
    this.error = '';

    this.gachaService.pull().subscribe({
      next: (result) => {
        // New result arrived — NOW replace the card with the new pull.
        // This happens in one frame so there's no gap between old and new.
        this.pulledItem = result.item;
        this.wasPity5   = result.was_pity_5;
        this.wasPity4   = result.was_pity_4;
        this.isLoading  = false;
        this.pullComplete.emit(result);
      },
      error: (err) => {
        this.error     = 'Pull failed — check that the PHP containers are running.';
        this.isLoading = false;
        console.error(err);
      }
    });
  }

  // Helper: returns the CSS class for the result card border colour
  // based on the rarity of the pulled item
  getRarityClass() {
    if (!this.pulledItem) return '';
    return `rarity-${this.pulledItem.rarity}`;
  }

  // Helper: returns star emoji string for the rarity
  // e.g. rarity 5 → '⭐⭐⭐⭐⭐'
  getStars(rarity: number) {
    return '⭐'.repeat(rarity);
  }
}
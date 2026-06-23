// frontend/src/app/components/history/history.component.ts
// Shows the last 10 pulls in a table, and a popup with the last 100.
// Receives history data from AppComponent via @Input().

import { Component, Input } from '@angular/core';

@Component({
  selector: 'app-history',
  templateUrl: './history.component.html',
  styleUrls: ['./history.component.css']
})
export class HistoryComponent {

  // Receives the full history object from AppComponent:
  // { recent: [...], full: [...], summary: { 5star, 4star, 3star, total } }
  @Input() history: any = null;

  // Controls whether the popup modal is visible
  showModal = false;

  openModal()  { this.showModal = true;  }
  closeModal() { this.showModal = false; }

  // Close modal if user clicks the dark backdrop (not the box inside it)
  onBackdropClick(event: MouseEvent) {
    // event.target is what was actually clicked
    // event.currentTarget is the backdrop div itself
    // Only close if they're the same — i.e. the user clicked the backdrop,
    // not something inside the modal box
    if (event.target === event.currentTarget) {
      this.closeModal();
    }
  }

  // Returns a CSS class based on rarity for row colouring
  getStarClass(rarity: number) {
    return `star-${rarity}`;
  }
}

// frontend/src/app/app.component.ts — root component.
// This is the top-level component that holds everything together.
// It owns the shared state (pity data, history) and passes it
// DOWN to child components via @Input() properties.

import { Component, OnInit } from '@angular/core';
import { GachaService } from './services/gacha.service';

// @Component is the decorator that marks this class as a component.
// selector    → the HTML tag: <app-root> in index.html
// templateUrl → the HTML template file for this component
// styleUrls   → CSS scoped to this component only
@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit {

  // Shared state — owned by AppComponent, passed down to children.
  // When these change, Angular automatically re-renders any component
  // that receives them as @Input() — no manual DOM manipulation needed.
  pityData: any = null;    // current pity counts + rates for PityBarComponent
  history: any = null;     // pull history for HistoryComponent

  // GachaService is injected automatically by Angular.
  // We don't write "new GachaService()" — Angular creates it and passes it in.
  constructor(private gachaService: GachaService) {}

  // ngOnInit runs once when the component first loads — like a constructor
  // but specifically for setup that needs the component to be ready.
  // Here we load the initial pity state and history from the API.
  ngOnInit() {
    this.loadStats();
    this.loadHistory();
  }

  // ── loadStats() ───────────────────────────────────────────────
  // Fetches pity counts from PHP so pity bars show correctly on load.
  loadStats() {
    this.gachaService.getStats().subscribe({
      next: (data) => {
        // data is the parsed JSON from stats.php
        // e.g. { pity: { count_5star: 23, count_4star: 7, ... } }
        this.pityData = data.pity;
      },
      error: (err) => console.error('Failed to load stats:', err)
    });
  }

  // ── loadHistory() ─────────────────────────────────────────────
  // Fetches pull history from PHP for the history table.
  loadHistory() {
    this.gachaService.getHistory().subscribe({
      next: (data) => {
        this.history = data;
      },
      error: (err) => console.error('Failed to load history:', err)
    });
  }

  // ── onPullComplete() ──────────────────────────────────────────
  // Called by PullComponent (child) after a successful pull via
  // an EventEmitter — the child tells the parent "a pull happened,
  // here's the new pity data". The parent then updates its state,
  // which flows back down to PityBarComponent and HistoryComponent.
  onPullComplete(result: any) {
    this.pityData = result.pity;  // update pity bars immediately
    this.loadHistory();            // reload history to show new pull
  }
}

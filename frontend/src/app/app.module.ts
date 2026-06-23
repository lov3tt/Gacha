// frontend/src/app/app.module.ts — the root module.
// A module is like a registry — it tells Angular which components,
// services, and built-in Angular features the app uses.
// Every Angular app has at least one module: AppModule.

import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule } from '@angular/common/http'; // lets us make HTTP calls to PHP API

// Components — each one must be declared here before Angular knows it exists
import { AppComponent } from './app.component';
import { PullComponent } from './components/pull/pull.component';
import { PityBarComponent } from './components/pity-bar/pity-bar.component';
import { HistoryComponent } from './components/history/history.component';

@NgModule({
  declarations: [
    // Every component you create must be listed here
    AppComponent,
    PullComponent,
    PityBarComponent,
    HistoryComponent,
  ],
  imports: [
    BrowserModule,     // core Angular browser functionality
    HttpClientModule,  // enables HttpClient for API calls (used in GachaService)
  ],
  bootstrap: [AppComponent] // AppComponent is the root — Angular starts here
})
export class AppModule {}

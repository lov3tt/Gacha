// frontend/src/main.ts — Angular entry point.
// This is the first file that runs. It bootstraps (starts) the app
// by loading AppModule, which in turn loads AppComponent.
// Think of it like index.php — the starting point everything flows from.

import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
import { AppModule } from './app/app.module';

platformBrowserDynamic()
  .bootstrapModule(AppModule)
  .catch(err => console.error(err));

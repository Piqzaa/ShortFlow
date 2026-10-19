import '@fontsource/dm-sans/latin-400.css';
import '@fontsource/dm-sans/latin-500.css';
import '@fontsource/dm-sans/latin-600.css';
import '@fontsource/dm-sans/latin-700.css';
import '@fontsource/space-grotesk/latin-500.css';
import './styles/app.css';
import './styles/experience.css';
import './styles/studio.css';
import './styles/cursor.css';
import './styles/library.css';
import './styles/legal.css';
import { Application } from '@hotwired/stimulus';
import StudioController from './controllers/studio_controller.js';
import AccountTokenController from './controllers/account_token_controller.js';
import CursorController from './controllers/cursor_controller.js';
import UploadController from './controllers/upload_controller.js';
const app = Application.start();
app.register('studio', StudioController);
app.register('account-token', AccountTokenController);
app.register('cursor', CursorController);
app.register('upload', UploadController);

import './styles/publication.css';
import StudioNavigationController from './controllers/studio_navigation_controller.js';
app.register('studio-navigation', StudioNavigationController);
import PublicationPreviewController from './controllers/publication_preview_controller.js';
app.register('publication-preview', PublicationPreviewController);
import SelectionController from './controllers/selection_controller.js';
app.register('selection', SelectionController);
import UnsavedController from './controllers/unsaved_controller.js';
app.register('unsaved', UnsavedController);
import './styles/dashboard.css';
import './styles/workspace.css';
import ComposerController from './controllers/composer_controller.js';
app.register('composer', ComposerController);
import CalendarController from './controllers/calendar_controller.js';
app.register('calendar', CalendarController);

import './styles/workspace-responsive.css';

import './styles/ai-studio.css';

import CreationController from './controllers/creation_controller.js';
app.register('creation', CreationController);
import OptionsController from './controllers/options_controller.js';
app.register('options', OptionsController);

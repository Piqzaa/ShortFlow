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
import VideoThumbnailsController from './controllers/video_thumbnails_controller.js';
app.register('video-thumbnails', VideoThumbnailsController);

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
import GenerationWatchController from './controllers/generation_watch_controller.js';
app.register('generation-watch', GenerationWatchController);
import OptionsController from './controllers/options_controller.js';
app.register('options', OptionsController);

import IdeaSaveController from './controllers/idea_save_controller.js';
app.register('idea-save', IdeaSaveController);

import ImageReferenceController from './controllers/image_reference_controller.js';
app.register('image-reference', ImageReferenceController);

import './styles/edit.css';
import './styles/editor-desk.css';
import EditController from './controllers/edit_controller.js';
import EditWatchController from './controllers/edit_watch_controller.js';
import EditPickerController from './controllers/edit_picker_controller.js';
app.register('edit', EditController);
app.register('edit-watch', EditWatchController);
app.register('edit-picker', EditPickerController);
import './styles/series.css';
import SeriesSettingsController from './controllers/series_settings_controller.js';
app.register('series-settings', SeriesSettingsController);
import './styles/episode.css';
import EpisodeWatchController from './controllers/episode_watch_controller.js';
app.register('episode-watch', EpisodeWatchController);

import ShotEditorController from './controllers/shot_editor_controller.js';
app.register('shot-editor', ShotEditorController);

import PreparationController from './controllers/preparation_controller.js';
app.register('preparation', PreparationController);

import StoryWriterController from './controllers/story_writer_controller.js';
app.register('story-writer', StoryWriterController);

import RecurrenceBudgetController from './controllers/recurrence_budget_controller.js';
app.register('recurrence-budget', RecurrenceBudgetController);

import SeriesReferenceController from './controllers/series_reference_controller.js';
app.register('series-reference', SeriesReferenceController);
import ReferencePickerController from './controllers/reference_picker_controller.js';
import SubjectListController from './controllers/subject_list_controller.js';
import SubjectUploadController from './controllers/subject_upload_controller.js';
import PublicationWriterController from './controllers/publication_writer_controller.js';
app.register('reference-picker', ReferencePickerController);
import ImagePreparationController from './controllers/image_preparation_controller.js';
app.register('image-preparation', ImagePreparationController);
app.register('subject-list', SubjectListController);
app.register('subject-upload', SubjectUploadController);
app.register('publication-writer', PublicationWriterController);

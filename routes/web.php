<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\ExtraNotesController;
use App\Http\Controllers\FutureUpdatesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\PhotosController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\VideosController;

//? the login logic
Route::get('/', [AuthController::class, 'showLoginForm']);
Route::post('/', [AuthController::class, 'login'])->name('login');
Route::post('/guest', [AuthController::class, 'guest'])->name('login.guest');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

//? the home page logic
Route::get('/homepage', [HomeController::class, 'showHomePage'])->middleware(['auth', 'log.visit'])->name('homepage');

Route::get('/visits', [HomeController::class, 'showAllVisits'])->middleware('auth')
    ->name('visits.all');
Route::delete('/visits/{visit}', [HomeController::class, 'deleteVisit'])->middleware('auth');
Route::delete('/visits', [HomeController::class, 'deleteAllVisits'])->middleware('auth');

//? the diary page
Route::get('/diary', [DiaryController::class, 'showDiaryPage'])->middleware(['auth', 'log.visit'])->name('diary');
Route::post('/diary', [DiaryController::class, 'storeEntry'])->middleware('auth')->name('diaryPage');
Route::patch('/diary/{entry}', [DiaryController::class, 'updateEntry'])->middleware('auth');
Route::delete('/diary/{entry}', [DiaryController::class, 'destroyEntry'])->middleware('auth');
Route::patch('/diary/{entry}/lock', [DiaryController::class, 'lockEntry'])->middleware('auth');
Route::patch('/diary/{entry}/unlock', [DiaryController::class, 'unlockEntry'])->middleware('auth');
Route::post('/diary/{entry}/reveal', [DiaryController::class, 'revealEntry'])->middleware('auth')->name('diary.reveal');

//? the extra notes page
//
// Only the page itself is a visit. Everything below it either answers with a turbo-stream
// (no navigation happens, so recording a page view would be a lie) or is a background GET
// fetching the next page of the stream.
//
// The bulk routes are declared BEFORE the /{note} ones: /extra_notes/bulk would otherwise be
// swallowed by the model-bound parameter and 404 looking for note "bulk".
Route::get('/extra_notes', [ExtraNotesController::class, 'showExtraNotesPage'])->middleware(['auth', 'log.visit'])->name('extra-notes');
Route::get('/extra_notes/messages', [ExtraNotesController::class, 'messages'])->middleware('auth')->name('extra-notes.messages');
Route::post('/extra_notes', [ExtraNotesController::class, 'storeNote'])->middleware('auth')->name('extra-notes.store');
Route::delete('/extra_notes/bulk', [ExtraNotesController::class, 'bulkDestroy'])->middleware('auth')->name('extra-notes.bulk-destroy');
Route::patch('/extra_notes/bulk/tags', [ExtraNotesController::class, 'bulkTags'])->middleware('auth')->name('extra-notes.bulk-tags');
Route::patch('/extra_notes/{note}', [ExtraNotesController::class, 'updateNote'])->middleware('auth')->whereNumber('note')->name('extra-notes.update');
Route::delete('/extra_notes/{note}', [ExtraNotesController::class, 'destroyNote'])->middleware('auth')->whereNumber('note')->name('extra-notes.destroy');
Route::patch('/extra_notes/{note}/pin', [ExtraNotesController::class, 'togglePin'])->middleware('auth')->whereNumber('note')->name('extra-notes.pin');
Route::patch('/extra_notes/{note}/tags', [ExtraNotesController::class, 'updateTags'])->middleware('auth')->whereNumber('note')->name('extra-notes.tags');

//? the projects page
Route::get('/projects', [ProjectsController::class, 'showProjectsPage'])->middleware(['auth', 'log.visit'])->name('projects');
Route::post('/projects', [ProjectsController::class, 'storeProject'])->middleware('auth')->name('projects.store');
Route::patch('/projects/{project}', [ProjectsController::class, 'updateProject'])->middleware('auth')->name('projects.update');
Route::patch('/projects/{project}/status', [ProjectsController::class, 'updateStatus'])->middleware('auth')->name('projects.status');
Route::delete('/projects/{project}', [ProjectsController::class, 'destroyProject'])->middleware('auth')->name('projects.destroy');
Route::get('/projects/{project}/files', [ProjectsController::class, 'listFiles'])->middleware('auth')->name('projects.files');

//? the Photos page
//
// Only the page and an album's page are visits. The upload endpoint is shared by the
// dialog's per-file fetch() and the plain form, and the streaming routes (view, thumbnail)
// are background GETs — none of them is a page someone navigated to.
//
// The bulk routes are declared BEFORE the /{photo} ones, and the model-bound ones are
// whereNumber'd: DELETE /photos/bulk would otherwise be swallowed by {photo} and 404
// looking for photo "bulk".
Route::get('/photos', [PhotosController::class, 'showPhotosPage'])->middleware(['auth', 'log.visit'])->name('photos');
Route::post('/photos', [PhotosController::class, 'storePhotos'])->middleware('auth')->name('photosPage');
Route::delete('/photos/bulk', [PhotosController::class, 'bulkDestroy'])->middleware('auth')->name('photos.bulk-destroy');
Route::patch('/photos/bulk/album', [PhotosController::class, 'bulkAlbum'])->middleware('auth')->name('photos.bulk-album');
Route::delete('/photos/{photo}', [PhotosController::class, 'destroyPhoto'])->middleware('auth')->whereNumber('photo');
Route::patch('/photos/{photo}/favorite', [PhotosController::class, 'toggleFavorite'])->middleware('auth')->whereNumber('photo');
Route::patch('/photos/{photo}/lock', [PhotosController::class, 'lockPhoto'])->middleware('auth')->whereNumber('photo');
Route::patch('/photos/{photo}/unlock', [PhotosController::class, 'unlockPhoto'])->middleware('auth')->whereNumber('photo');
Route::get('/photos/{photo}/view', [PhotosController::class, 'viewPhoto'])->middleware('auth')->whereNumber('photo')->name('photo.view');
Route::get('/photos/{photo}/thumbnail', [PhotosController::class, 'viewThumbnail'])->middleware('auth')->whereNumber('photo')->name('photo.thumbnail');

Route::post('/albums', [PhotosController::class, 'storeAlbum'])->middleware('auth')->name('albums.store');
Route::patch('/albums/{album}', [PhotosController::class, 'updateAlbum'])->middleware('auth')->name('albums.update');
Route::delete('/albums/{album}', [PhotosController::class, 'destroyAlbum'])->middleware('auth')->name('albums.destroy');
Route::get('/albums/{album}', [PhotosController::class, 'showAlbum'])->middleware('auth')->name('albums.show');

//? the Videos page
//
// Only the page and an album's page are visits. The poster and the stream are background
// GETs the browser makes for a card or the watch dialog, and /videos/watch/{slug} only
// redirects — none of the three is a page someone navigated to.
//
// The model-bound routes are whereNumber'd so /videos/watch/... can never be swallowed by
// {video} and 404 looking for a video called "watch".
Route::get('/videos', [VideosController::class, 'showVideosPage'])->middleware(['auth', 'log.visit'])->name('videos');
Route::post('/videos', [VideosController::class, 'storeVideo'])->middleware('auth')->name('videosPage');
Route::get('/videos/watch/{slug}', [VideosController::class, 'watchBySlug'])->middleware('auth')->name('video.slug');
Route::delete('/videos/{video}', [VideosController::class, 'destroyVideo'])->middleware('auth')->whereNumber('video');
Route::patch('/videos/{video}/favorite', [VideosController::class, 'toggleFavorite'])->middleware('auth')->whereNumber('video');
Route::patch('/videos/{video}/lock', [VideosController::class, 'lockVideo'])->middleware('auth')->whereNumber('video');
Route::patch('/videos/{video}/unlock', [VideosController::class, 'unlockVideo'])->middleware('auth')->whereNumber('video');
Route::get('/videos/{video}/stream', [VideosController::class, 'streamVideo'])->middleware('auth')->whereNumber('video')->name('video.stream');
Route::get('/videos/{video}/poster', [VideosController::class, 'viewPoster'])->middleware('auth')->whereNumber('video')->name('video.poster');

Route::post('/video-albums', [VideosController::class, 'storeAlbum'])->middleware('auth')->name('video-albums.store');
Route::patch('/video-albums/{album}', [VideosController::class, 'updateAlbum'])->middleware('auth')->name('video-albums.update');
Route::delete('/video-albums/{album}', [VideosController::class, 'destroyAlbum'])->middleware('auth')->name('video-albums.destroy');
Route::get('/video-albums/{album}', [VideosController::class, 'showAlbum'])->middleware(['auth', 'log.visit'])->name('video-albums.show');

//? the Future Updates page
//
// Only the page itself is a visit. The mutations all redirect back (Turbo Drive rejects a
// 200 page response from a form), and back() follows the Referer, so the layout / show-done
// / project-filter state the page is holding in its query string comes back with them.
Route::get('/future_updates', [FutureUpdatesController::class, 'showFutureUpdatesPage'])->middleware(['auth', 'log.visit'])->name('future-updates');
Route::post('/future_updates', [FutureUpdatesController::class, 'storeUpdate'])->middleware('auth')->name('future-updates.store');
Route::patch('/future_updates/{update}', [FutureUpdatesController::class, 'editUpdate'])->middleware('auth')->name('future-updates.update');
Route::patch('/future_updates/{update}/done', [FutureUpdatesController::class, 'toggleDone'])->middleware('auth')->name('future-updates.done');
Route::delete('/future_updates/{update}', [FutureUpdatesController::class, 'destroyUpdate'])->middleware('auth')->name('future-updates.destroy');

//? Music logic
Route::get('/music', [MusicController::class, 'viewMusicPage'])->middleware(['auth', 'log.visit'])->name('music');
Route::post('/music', [MusicController::class, 'storeMusic'])->middleware('auth')->name('musicPage');
Route::patch('/music/{track}', [MusicController::class, 'updateMusic'])->middleware('auth');
Route::delete('/music/{track}', [MusicController::class, 'destroyMusic'])->middleware('auth');
Route::patch('/music/{track}/favorite', [MusicController::class, 'toggleFavorite'])->middleware('auth');
Route::get('/music/queue', [MusicController::class, 'queue'])->middleware('auth')->name('music.queue');
Route::get('/music/{track}/stream', [MusicController::class, 'streamTrack'])->middleware('auth')->name('music.stream');

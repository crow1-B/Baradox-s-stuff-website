@php
    /**
     * Every dialog the board needs, shared by the Photos page and an album's page.
     *
     * The forms live here, at page level, and never inside a tile: JS points the right one
     * at the right photo and submits it. One form per tile would be several hundred forms
     * on a real library, for no gain — nothing here is ever submitted without JS anyway,
     * since the buttons that open these dialogs are buttons.
     *
     * $manageAlbums adds the create/rename/delete-album dialogs; only the Photos page shows
     * the album strip, so only it passes true.
     */
    $manageAlbums = $manageAlbums ?? false;
    $pickable = $albums->where('is_system', false)->values();
@endphp

{{-- Page-wide drop hint. Pointer-events are off in CSS, so it can never eat a click. --}}
<div class="ph-dropzone" data-ph-dropzone hidden>
    <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
    <p>Drop the photos to upload them</p>
</div>

{{-- Favourite ----------------------------------------------------------- --}}
{{-- No dialog, just the one form the star buttons all share. --}}
<form method="POST" action="" class="hidden" data-ph-fav-form>
    @csrf
    @method('PATCH')
</form>

{{-- Lightbox ------------------------------------------------------------ --}}
<dialog class="ph-lightbox" id="ph-lightbox" aria-label="Photo viewer">
    <div class="ph-lb__head">
        <button type="button" class="ph-btn ph-btn--ghost ph-btn--icon" data-ph-close aria-label="Close viewer">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <span class="ph-lb__pos" data-ph-lb-pos aria-live="polite"></span>
        <span class="ph-lb__meta">
            <span class="ph-badge" data-ph-lb-badge hidden></span>
            <span class="ph-faint text-sm" data-ph-lb-added></span>
        </span>
    </div>

    <div class="ph-lb__stage">
        <button type="button" class="ph-lb__nav ph-lb__nav--prev" data-ph-lb-prev aria-label="Previous photo">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>

        <img class="ph-lb__img" data-ph-lb-img alt="" hidden>
        <div class="ph-lb__spinner" data-ph-lb-spinner role="status" aria-label="Loading" hidden></div>

        {{-- The key prompt lives inside the viewer, so arrowing onto a protected photo asks
             here rather than throwing you back to the grid. The key is held for exactly one
             photo: every move re-arms this. --}}
        <div class="ph-lb__gate" data-ph-lb-gate hidden>
            <i class="fa-solid fa-lock ph-lb__gate-icon" aria-hidden="true"></i>
            <div>
                <h3 class="text-base" data-ph-lb-gate-title>This photo is protected</h3>
                <p class="ph-muted text-sm mt-1" data-ph-lb-gate-note></p>
            </div>
            {{-- data-turbo="false" and preventDefault(): this form has nowhere to go, it just
                 gives Enter something to do. --}}
            <form data-ph-lb-key-form data-turbo="false">
                <input type="password" class="ph-input" data-ph-lb-key autocomplete="off"
                       placeholder="Key" aria-label="Key for this photo">
                <button type="submit" class="ph-btn ph-btn--primary">Show photo</button>
            </form>
            <p class="ph-error" data-ph-lb-key-error role="alert" hidden></p>
        </div>

        <button type="button" class="ph-lb__nav ph-lb__nav--next" data-ph-lb-next aria-label="Next photo">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
    </div>

    <div class="ph-lb__foot">
        <button type="button" class="ph-btn ph-btn--sm" data-ph-action="favorite" data-ph-lb-fav>
            <i class="fa-regular fa-star" aria-hidden="true"></i>
            <span data-ph-lb-fav-label>Favourite</span>
        </button>
        <button type="button" class="ph-btn ph-btn--sm" data-ph-action="lock" data-ph-lb-lock>
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Lock
        </button>
        <button type="button" class="ph-btn ph-btn--sm" data-ph-action="unlock" data-ph-lb-unlock hidden>
            <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
            Unlock
        </button>
        <button type="button" class="ph-btn ph-btn--sm ph-btn--danger" data-ph-action="delete" data-ph-lb-delete>
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
            Delete
        </button>
    </div>
</dialog>

{{-- Lock ---------------------------------------------------------------- --}}
<dialog class="ph-dialog" id="ph-lock-dialog" aria-labelledby="ph-lock-title">
    <form method="POST" action="" data-ph-lock-form>
        @csrf
        @method('PATCH')
        <div class="ph-dialog__head">
            <h2 class="ph-dialog__title" id="ph-lock-title">Lock this photo</h2>
            <button type="button" class="ph-btn ph-btn--ghost ph-btn--sm ph-btn--icon" data-ph-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="ph-dialog__body">
            <div class="flex flex-col gap-4">
                <div class="ph-field">
                    <label class="ph-label" for="ph-lock-key">Key<span class="ph-req" aria-hidden="true">*</span></label>
                    <input class="ph-input" id="ph-lock-key" name="key" type="password" required minlength="8"
                           autocomplete="new-password" aria-describedby="ph-lock-key-hint">
                    <p class="ph-hint" id="ph-lock-key-hint">
                        At least 8 characters. The key is never stored — lose it and the photo is gone for good.
                    </p>
                </div>

                <div class="ph-field">
                    <label class="ph-label" for="ph-lock-mode">How<span class="ph-req" aria-hidden="true">*</span></label>
                    <select class="ph-input" id="ph-lock-mode" name="mode" aria-describedby="ph-lock-mode-hint">
                        <option value="gated">Gated — hidden behind the key</option>
                        <option value="encrypted">Encrypted — the file itself is scrambled</option>
                    </select>
                    <p class="ph-hint" id="ph-lock-mode-hint">
                        Gated is access control, not encryption: the file stays readable on the drive and only this
                        site asks for the key. Encrypted rewrites the file with AES-256-CBC and decrypts it per view.
                    </p>
                </div>
            </div>
        </div>
        <div class="ph-dialog__foot">
            <button type="button" class="ph-btn" data-ph-close>Cancel</button>
            <button type="submit" class="ph-btn ph-btn--primary">Lock photo</button>
        </div>
    </form>
</dialog>

{{-- Unlock -------------------------------------------------------------- --}}
<dialog class="ph-dialog ph-dialog--sm" id="ph-unlock-dialog" aria-labelledby="ph-unlock-title">
    <form method="POST" action="" data-ph-unlock-form>
        @csrf
        @method('PATCH')
        <div class="ph-dialog__head">
            <h2 class="ph-dialog__title" id="ph-unlock-title">Unlock this photo</h2>
            <button type="button" class="ph-btn ph-btn--ghost ph-btn--sm ph-btn--icon" data-ph-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="ph-dialog__body">
            <div class="ph-field">
                <label class="ph-label" for="ph-unlock-key">Key<span class="ph-req" aria-hidden="true">*</span></label>
                <input class="ph-input" id="ph-unlock-key" name="key" type="password" required autocomplete="off">
                <p class="ph-hint">The photo goes back to being served to anyone signed in here.</p>
            </div>
        </div>
        <div class="ph-dialog__foot">
            <button type="button" class="ph-btn" data-ph-close>Cancel</button>
            <button type="submit" class="ph-btn ph-btn--primary">Unlock</button>
        </div>
    </form>
</dialog>

{{-- Delete one ---------------------------------------------------------- --}}
<dialog class="ph-dialog ph-dialog--sm" id="ph-delete-dialog" aria-labelledby="ph-delete-title" aria-describedby="ph-delete-note">
    <form method="POST" action="" data-ph-delete-form>
        @csrf
        @method('DELETE')
        <div class="ph-dialog__head">
            <h2 class="ph-dialog__title" id="ph-delete-title">Delete this photo?</h2>
        </div>
        <div class="ph-dialog__body">
            <p class="ph-muted text-sm" id="ph-delete-note">
                The file and its thumbnail are removed from the drive. There is no undo.
                A protected photo needs no key to be deleted — deleting is not reading it.
            </p>
        </div>
        <div class="ph-dialog__foot">
            <button type="button" class="ph-btn" data-ph-close autofocus>Cancel</button>
            <button type="submit" class="ph-btn ph-btn--danger">Delete</button>
        </div>
    </form>
</dialog>

{{-- Bulk delete --------------------------------------------------------- --}}
<dialog class="ph-dialog ph-dialog--sm" id="ph-bulk-delete-dialog" aria-labelledby="ph-bulk-delete-title">
    <form method="POST" action="{{ route('photos.bulk-destroy') }}" data-ph-bulk-delete-form>
        @csrf
        @method('DELETE')
        <span data-ph-ids></span>
        <div class="ph-dialog__head">
            <h2 class="ph-dialog__title" id="ph-bulk-delete-title">Delete <span data-ph-bulk-count></span>?</h2>
        </div>
        <div class="ph-dialog__body">
            <p class="ph-muted text-sm">
                Their files and thumbnails are removed from the drive. There is no undo.
            </p>
        </div>
        <div class="ph-dialog__foot">
            <button type="button" class="ph-btn" data-ph-close autofocus>Cancel</button>
            <button type="submit" class="ph-btn ph-btn--danger">Delete</button>
        </div>
    </form>
</dialog>

{{-- Bulk album ---------------------------------------------------------- --}}
<dialog class="ph-dialog ph-dialog--sm" id="ph-bulk-album-dialog" aria-labelledby="ph-bulk-album-title">
    <form method="POST" action="{{ route('photos.bulk-album') }}" data-ph-bulk-album-form>
        @csrf
        @method('PATCH')
        <input type="hidden" name="mode" value="add" data-ph-bulk-mode>
        <span data-ph-ids></span>
        <div class="ph-dialog__head">
            <h2 class="ph-dialog__title" id="ph-bulk-album-title" data-ph-bulk-album-title>Add to album</h2>
            <button type="button" class="ph-btn ph-btn--ghost ph-btn--sm ph-btn--icon" data-ph-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="ph-dialog__body">
            @if ($pickable->isEmpty())
                <p class="ph-muted text-sm">There are no albums yet. Create one first.</p>
            @else
                <div class="ph-field">
                    <label class="ph-label" for="ph-bulk-album">Album<span class="ph-req" aria-hidden="true">*</span></label>
                    <select class="ph-input" id="ph-bulk-album" name="album_id" required>
                        @foreach ($pickable as $pick)
                            <option value="{{ $pick->id }}" @selected(isset($album) && $album && $album->id === $pick->id)>{{ $pick->name }}</option>
                        @endforeach
                    </select>
                    <p class="ph-hint" data-ph-bulk-album-hint></p>
                </div>
            @endif
        </div>
        <div class="ph-dialog__foot">
            <button type="button" class="ph-btn" data-ph-close>Cancel</button>
            <button type="submit" class="ph-btn ph-btn--primary" @disabled($pickable->isEmpty())>Apply</button>
        </div>
    </form>
</dialog>

{{-- Upload -------------------------------------------------------------- --}}
<dialog class="ph-dialog" id="ph-upload-dialog" aria-labelledby="ph-upload-title">
    <form method="POST" action="{{ route('photosPage') }}" enctype="multipart/form-data" data-ph-upload-form>
        @csrf
        <div class="ph-dialog__head">
            <h2 class="ph-dialog__title" id="ph-upload-title">Upload photos</h2>
            <button type="button" class="ph-btn ph-btn--ghost ph-btn--sm ph-btn--icon" data-ph-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="ph-dialog__body">
            <div class="flex flex-col gap-4">
                <label class="ph-drop" data-ph-drop>
                    <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    <span><strong>Choose files</strong> or drop them here</span>
                    <span class="ph-hint">jpg, jpeg, png, webp or gif — up to 20 MB each</span>
                    <input type="file" name="photos[]" class="ph-sr" multiple accept="image/jpeg,image/png,image/webp,image/gif" data-ph-file-input>
                </label>

                <ul class="ph-queue" data-ph-queue role="list"></ul>

                <div class="ph-field">
                    <label class="ph-label" for="ph-upload-album">Album</label>
                    <select class="ph-input" id="ph-upload-album" name="album_name" data-ph-album-select>
                        <option value="">No album</option>
                        @foreach ($pickable as $pick)
                            <option value="{{ $pick->name }}" @selected(isset($album) && $album && $album->id === $pick->id)>{{ $pick->name }}</option>
                        @endforeach
                        <option value="__new__">+ Create a new album…</option>
                    </select>
                </div>

                <div class="ph-field" data-ph-new-album hidden>
                    <label class="ph-label" for="ph-upload-new-album">New album name<span class="ph-req" aria-hidden="true">*</span></label>
                    <input class="ph-input" id="ph-upload-new-album" name="new_album_name" type="text" autocomplete="off" maxlength="120">
                </div>

                {{-- Set by JS; without it the server falls back to "a name was given, so it
                     must already exist" — which is the old, unchanged hard-error path. --}}
                <input type="hidden" name="album_mode" value="" data-ph-album-mode>
            </div>
        </div>

        <div class="ph-dialog__foot">
            <button type="button" class="ph-btn" data-ph-close>Close</button>
            <button type="submit" class="ph-btn ph-btn--primary" data-ph-upload-submit disabled>Upload</button>
        </div>
    </form>
</dialog>

@if ($manageAlbums)
    {{-- Album create / rename ------------------------------------------- --}}
    <dialog class="ph-dialog ph-dialog--sm" id="ph-album-dialog" aria-labelledby="ph-album-title">
        <form method="POST" action="{{ route('albums.store') }}" data-ph-album-form>
            @csrf
            <span data-ph-album-method></span>
            <div class="ph-dialog__head">
                <h2 class="ph-dialog__title" id="ph-album-title" data-ph-album-heading>New album</h2>
                <button type="button" class="ph-btn ph-btn--ghost ph-btn--sm ph-btn--icon" data-ph-close aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="ph-dialog__body">
                <div class="ph-field">
                    <label class="ph-label" for="ph-album-name">Name<span class="ph-req" aria-hidden="true">*</span></label>
                    <input class="ph-input" id="ph-album-name" name="name" type="text" required maxlength="120" autocomplete="off">
                </div>
            </div>
            <div class="ph-dialog__foot">
                <button type="button" class="ph-btn" data-ph-close>Cancel</button>
                <button type="submit" class="ph-btn ph-btn--primary" data-ph-album-submit>Create album</button>
            </div>
        </form>
    </dialog>

    {{-- Album delete ---------------------------------------------------- --}}
    <dialog class="ph-dialog ph-dialog--sm" id="ph-album-delete-dialog" aria-labelledby="ph-album-delete-title">
        <form method="POST" action="" data-ph-album-delete-form>
            @csrf
            @method('DELETE')
            <div class="ph-dialog__head">
                <h2 class="ph-dialog__title" id="ph-album-delete-title">Delete this album?</h2>
            </div>
            <div class="ph-dialog__body">
                <p class="ph-muted text-sm">
                    “<span data-ph-album-delete-name></span>” goes away. The photos in it stay —
                    an album is only a grouping.
                </p>
            </div>
            <div class="ph-dialog__foot">
                <button type="button" class="ph-btn" data-ph-close autofocus>Cancel</button>
                <button type="submit" class="ph-btn ph-btn--danger">Delete album</button>
            </div>
        </form>
    </dialog>
@endif

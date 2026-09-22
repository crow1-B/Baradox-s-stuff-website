@php
    /**
     * Every dialog the board needs, shared by the Videos page and an album's page.
     *
     * The forms live here, at page level, never inside a card: JS points the right one at
     * the right video and submits it. A card is a thing that gets re-rendered, and a form
     * inside one would be several forms per card for no gain.
     *
     * $manageAlbums adds the create/rename/delete-album dialogs; only the Videos page shows
     * the album strip, so only it passes true.
     */
    $manageAlbums = $manageAlbums ?? false;
    $pickable = $albums->where('is_system', false)->values();
    $preselect = (isset($album) && $album && ! $album->is_system) ? $album->id : null;
@endphp

{{-- Favourite ----------------------------------------------------------- --}}
{{-- No dialog, just the one form every star button shares. --}}
<form method="POST" action="" class="hidden" data-vd-fav-form>
    @csrf
    @method('PATCH')
</form>

{{-- Watch --------------------------------------------------------------- --}}
{{-- The <video> inside this dialog is the ONLY one on the page, and it has no src until
     the dialog opens. That is the whole point of the rebuild: nothing touches a multi-GB
     file until someone asks to watch it. Closing tears the src back off. --}}
<dialog class="vd-player" id="vd-player-dialog" aria-label="Video player">
    <div class="vd-pl__head">
        <button type="button" class="vd-btn vd-btn--ghost vd-btn--icon" data-vd-close aria-label="Close player">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <h2 class="vd-pl__title" data-vd-pl-title dir="auto"></h2>
        <span class="vd-pl__meta">
            <span class="vd-badge" data-vd-pl-badge hidden>
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Gated
            </span>
            <span class="vd-faint text-sm" data-vd-pl-len></span>
        </span>
    </div>

    <div class="vd-pl__stage">
        {{-- preload="none" as well as an absent src: belt and braces, so a stale snapshot
             restored by the browser can never start fetching on its own. --}}
        <video class="vd-pl__video" data-vd-video controls playsinline preload="none" hidden></video>

        {{-- The key prompt lives HERE, not on the card. A card is for scanning; a key
             prompt sitting inline on one was the old page's worst habit — it put a live
             overlay across the page the moment you clicked a gated video's thumbnail. --}}
        <div class="vd-pl__gate" data-vd-pl-gate hidden>
            <i class="fa-solid fa-lock vd-pl__gate-icon" aria-hidden="true"></i>
            <div>
                <h3 class="text-base">This video is gated</h3>
                <p class="vd-muted text-sm mt-1">
                    The key isn't stored anywhere and isn't remembered between videos. Gating is access
                    control, not encryption — the file itself stays readable on the drive.
                </p>
            </div>
            {{-- data-turbo="false" and preventDefault(): this form has nowhere to go, it just
                 gives Enter something to do. --}}
            <form data-vd-key-form data-turbo="false">
                <input type="password" class="vd-input" data-vd-key autocomplete="off"
                       placeholder="Key" aria-label="Key for this video">
                <button type="submit" class="vd-btn vd-btn--primary">Play video</button>
            </form>
            <p class="vd-error" data-vd-key-error role="alert" hidden></p>
        </div>

        <div class="vd-pl__failed" data-vd-pl-failed hidden>
            <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
            <div>
                <h3 class="text-base">That didn't play</h3>
                <p class="vd-muted text-sm mt-1" data-vd-pl-failed-note></p>
            </div>
        </div>
    </div>

    <div class="vd-pl__foot">
        <p class="vd-faint text-sm" data-vd-pl-file dir="auto"></p>
        <span class="vd-pl__keys">
            <kbd>Space</kbd> play/pause · <kbd>←</kbd><kbd>→</kbd> 5s · <kbd>Esc</kbd> close
        </span>
    </div>
</dialog>

{{-- Register ------------------------------------------------------------ --}}
<dialog class="vd-dialog" id="vd-register-dialog" aria-labelledby="vd-register-title">
    <form method="POST" action="{{ route('videosPage') }}" data-vd-register-form>
        @csrf
        <div class="vd-dialog__head">
            <h2 class="vd-dialog__title" id="vd-register-title">Register a video</h2>
            <button type="button" class="vd-btn vd-btn--ghost vd-btn--sm vd-btn--icon" data-vd-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="vd-dialog__body">
            <div class="flex flex-col gap-4">
                <p class="vd-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>
                        The site never receives video uploads — a multi-GB file goes onto the drive
                        through Windows, and this registers the copy that is already there.
                        Everything is read from <code>{{ $folderLabel }}</code>.
                    </span>
                </p>

                {{-- The picker: what is on the drive with no row pointing at it yet. Typing a
                     long filename by hand is the step that goes wrong, so it is no longer the
                     only way — but free text stays, for a file that appeared after this page
                     rendered.

                     Buttons, not radios: the form must carry exactly one filename field, and a
                     grouped radio would post a second, ignored parameter beside it. They write
                     straight into the input below, which is the only thing submitted. --}}
                <div class="vd-field">
                    <span class="vd-label" id="vd-file-label">File<span class="vd-req" aria-hidden="true">*</span></span>

                    @if (! $driveReady)
                        <p class="vd-hint">The drive isn't mounted, so nothing can be listed or registered right now.</p>
                    @elseif (count($available) === 0)
                        <p class="vd-hint">
                            Every video file in that folder already has an entry. Drop a new one in and
                            reload the page, or type a name below.
                        </p>
                    @else
                        <ul class="vd-files" role="list" data-vd-files aria-labelledby="vd-file-label">
                            @foreach ($available as $file)
                                <li>
                                    <button type="button" class="vd-file" data-vd-pick data-name="{{ $file['name'] }}" aria-pressed="false">
                                        <i class="fa-regular fa-file-video" aria-hidden="true"></i>
                                        <span class="vd-file__name" dir="auto">{{ $file['name'] }}</span>
                                        <span class="vd-file__size">{{ $file['size'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <label class="vd-check mt-1">
                        <input type="checkbox" data-vd-manual @checked($errors->has('filename') || count($available) === 0 || ! $driveReady)>
                        <span>Type the file name instead</span>
                    </label>
                </div>

                {{-- The one and only filename field. The picker writes into it, so the server
                     sees the same parameter whichever way it was filled. `required` is applied
                     by JS only while this is visible — a hidden required input makes Chrome
                     refuse to submit and give no reason. --}}
                <div class="vd-field" data-vd-manual-field @if (! ($errors->has('filename') || count($available) === 0 || ! $driveReady)) hidden @endif>
                    <label class="vd-label" for="vd-filename">File name, exactly as it is on the drive</label>
                    <input class="vd-input" id="vd-filename" name="filename" type="text" maxlength="255"
                           autocomplete="off" spellcheck="false" dir="auto"
                           value="{{ old('filename') }}"
                           aria-invalid="{{ $errors->has('filename') ? 'true' : 'false' }}"
                           placeholder="trip-to-karbala.mp4"
                           data-vd-filename>
                    @error('filename')
                        <p class="vd-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- What the picker chose, when the field above is collapsed. --}}
                <p class="vd-picked" data-vd-picked hidden>
                    <i class="fa-regular fa-file-video" aria-hidden="true"></i>
                    <span data-vd-picked-name dir="auto"></span>
                </p>

                <div class="vd-field">
                    <label class="vd-label" for="vd-title">Title<span class="vd-req" aria-hidden="true">*</span></label>
                    <input class="vd-input" id="vd-title" name="title" type="text" required maxlength="200"
                           autocomplete="off" dir="auto" value="{{ old('title') }}">
                    <p class="vd-hint">
                        The link is built from this once and never rebuilt, so a link already pasted
                        somewhere keeps working if the title changes later.
                    </p>
                </div>

                <div class="vd-field">
                    <label class="vd-label" for="vd-description">Description</label>
                    <textarea class="vd-input vd-textarea" id="vd-description" name="description"
                              rows="3" maxlength="2000" dir="auto">{{ old('description') }}</textarea>
                </div>

                <div class="vd-field">
                    <label class="vd-label" for="vd-album">Album</label>
                    <select class="vd-input" id="vd-album" name="album_id">
                        <option value="">No album</option>
                        @foreach ($pickable as $pick)
                            <option value="{{ $pick->id }}" @selected((int) old('album_id', $preselect) === $pick->id)>{{ $pick->name }}</option>
                        @endforeach
                    </select>
                    <p class="vd-hint">Length and a poster frame are read from the file when it is registered.</p>
                </div>
            </div>
        </div>

        <div class="vd-dialog__foot">
            <button type="button" class="vd-btn" data-vd-close>Cancel</button>
            <button type="submit" class="vd-btn vd-btn--primary" data-vd-register-submit @disabled(! $driveReady)>Register</button>
        </div>
    </form>
</dialog>

{{-- Lock ---------------------------------------------------------------- --}}
<dialog class="vd-dialog vd-dialog--sm" id="vd-lock-dialog" aria-labelledby="vd-lock-title">
    <form method="POST" action="" data-vd-lock-form>
        @csrf
        @method('PATCH')
        <div class="vd-dialog__head">
            <h2 class="vd-dialog__title" id="vd-lock-title">Gate this video</h2>
            <button type="button" class="vd-btn vd-btn--ghost vd-btn--sm vd-btn--icon" data-vd-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="vd-dialog__body">
            <div class="vd-field">
                <label class="vd-label" for="vd-lock-key">Key<span class="vd-req" aria-hidden="true">*</span></label>
                <input class="vd-input" id="vd-lock-key" name="key" type="password" required minlength="8"
                       autocomplete="new-password" aria-describedby="vd-lock-hint">
                <p class="vd-hint" id="vd-lock-hint">
                    At least 8 characters, and it is never stored — only a hash of it is. Gating is access
                    control, <strong>not</strong> encryption: the file stays readable on the drive and only
                    this site asks for the key. The poster is pixelated while it is gated.
                </p>
            </div>
        </div>
        <div class="vd-dialog__foot">
            <button type="button" class="vd-btn" data-vd-close>Cancel</button>
            <button type="submit" class="vd-btn vd-btn--primary">Gate video</button>
        </div>
    </form>
</dialog>

{{-- Unlock -------------------------------------------------------------- --}}
<dialog class="vd-dialog vd-dialog--sm" id="vd-unlock-dialog" aria-labelledby="vd-unlock-title">
    <form method="POST" action="" data-vd-unlock-form>
        @csrf
        @method('PATCH')
        <div class="vd-dialog__head">
            <h2 class="vd-dialog__title" id="vd-unlock-title">Unlock this video</h2>
            <button type="button" class="vd-btn vd-btn--ghost vd-btn--sm vd-btn--icon" data-vd-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="vd-dialog__body">
            <div class="vd-field">
                <label class="vd-label" for="vd-unlock-key">Key<span class="vd-req" aria-hidden="true">*</span></label>
                <input class="vd-input" id="vd-unlock-key" name="key" type="password" required autocomplete="off">
                <p class="vd-hint">The video goes back to being played by anyone signed in here.</p>
            </div>
        </div>
        <div class="vd-dialog__foot">
            <button type="button" class="vd-btn" data-vd-close>Cancel</button>
            <button type="submit" class="vd-btn vd-btn--primary">Unlock</button>
        </div>
    </form>
</dialog>

{{-- Delete -------------------------------------------------------------- --}}
<dialog class="vd-dialog vd-dialog--sm" id="vd-delete-dialog" aria-labelledby="vd-delete-title" aria-describedby="vd-delete-note">
    <form method="POST" action="" data-vd-delete-form>
        @csrf
        @method('DELETE')
        <div class="vd-dialog__head">
            <h2 class="vd-dialog__title" id="vd-delete-title">Remove this video?</h2>
        </div>
        <div class="vd-dialog__body">
            <p class="vd-muted text-sm" id="vd-delete-note">
                The entry and its cached poster go. <strong>The video file stays on the drive</strong> —
                it was put there by hand and this site has never owned it. A gated video needs no key
                to be removed; removing is not watching it.
            </p>
        </div>
        <div class="vd-dialog__foot">
            <button type="button" class="vd-btn" data-vd-close autofocus>Cancel</button>
            <button type="submit" class="vd-btn vd-btn--danger">Remove entry</button>
        </div>
    </form>
</dialog>

@if ($manageAlbums)
    {{-- Album create / rename ------------------------------------------- --}}
    <dialog class="vd-dialog vd-dialog--sm" id="vd-album-dialog" aria-labelledby="vd-album-title">
        <form method="POST" action="{{ route('video-albums.store') }}" data-vd-album-form>
            @csrf
            <span data-vd-album-method></span>
            <div class="vd-dialog__head">
                <h2 class="vd-dialog__title" id="vd-album-title" data-vd-album-heading>New album</h2>
                <button type="button" class="vd-btn vd-btn--ghost vd-btn--sm vd-btn--icon" data-vd-close aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="vd-dialog__body">
                <div class="vd-field">
                    <label class="vd-label" for="vd-album-name">Name<span class="vd-req" aria-hidden="true">*</span></label>
                    <input class="vd-input" id="vd-album-name" name="name" type="text" required maxlength="120" autocomplete="off" dir="auto">
                </div>
            </div>
            <div class="vd-dialog__foot">
                <button type="button" class="vd-btn" data-vd-close>Cancel</button>
                <button type="submit" class="vd-btn vd-btn--primary" data-vd-album-submit>Create album</button>
            </div>
        </form>
    </dialog>

    {{-- Album delete ---------------------------------------------------- --}}
    <dialog class="vd-dialog vd-dialog--sm" id="vd-album-delete-dialog" aria-labelledby="vd-album-delete-title">
        <form method="POST" action="" data-vd-album-delete-form>
            @csrf
            @method('DELETE')
            <div class="vd-dialog__head">
                <h2 class="vd-dialog__title" id="vd-album-delete-title">Delete this album?</h2>
            </div>
            <div class="vd-dialog__body">
                <p class="vd-muted text-sm">
                    “<span data-vd-album-delete-name></span>” goes away. The videos in it stay —
                    an album is only a grouping.
                </p>
            </div>
            <div class="vd-dialog__foot">
                <button type="button" class="vd-btn" data-vd-close autofocus>Cancel</button>
                <button type="submit" class="vd-btn vd-btn--danger">Delete album</button>
            </div>
        </form>
    </dialog>
@endif

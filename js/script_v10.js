// js/script_v2.js

jQuery(document).ready(function ($) {
    const playerContainer = $('#player-container');
    const playerElement = $('#radio-player');
    const radioSource = $('#radio-source');
    const stationLogoImg = $('#station-logo');
    const stationGrid = $('.radio-station-grid');
    const openPopupButton = $('#yc-open-popup-player-btn');

    const defaultLogo = (typeof ycRadioPlayer !== 'undefined' && ycRadioPlayer.defaultLogoUrl) ? ycRadioPlayer.defaultLogoUrl : '';
    let popupPlayerWindow = null; // Keep track of the popup window

    // Function to pause the main player
    function pauseMainPlayer() {
        if (playerElement.length && playerElement[0] && !playerElement[0].paused) {
            playerElement[0].pause();
        }
    }

    function playStation(stationUrl, stationLogoUrl, stationName) {
        if (playerElement.length && radioSource.length) {
            const currentStationUrl = stationUrl || '';
            const currentStationLogoUrl = stationLogoUrl || defaultLogo;
            const currentStationName = stationName || 'Radio Station';

            radioSource.attr('src', currentStationUrl);
            playerElement[0].src = currentStationUrl;
            playerElement[0].load();
            playerElement[0].play().catch(error => console.warn("Main Player: Playback prevented.", error));

            if (stationLogoImg.length) {
                stationLogoImg.attr('src', currentStationLogoUrl);
                stationLogoImg.attr('alt', currentStationName + ' Logo');
            }

            if (playerContainer.length) {
                playerContainer.attr('data-current-station-url', currentStationUrl);
                playerContainer.attr('data-current-station-logo', currentStationLogoUrl);
                playerContainer.attr('data-current-station-name', currentStationName);
            }

            const d = new Date();
            d.setTime(d.getTime() + (7 * 24 * 60 * 60 * 1000));
            let expires = "expires=" + d.toUTCString();
            document.cookie = "selected_station_url=" + encodeURIComponent(currentStationUrl) + ";" + expires + ";path=/";
            document.cookie = "selected_station_logo=" + encodeURIComponent(currentStationLogoUrl) + ";" + expires + ";path=/";
        }
    }

    if (stationGrid.length) {
        stationGrid.on('click keypress', '.station-grid-item', function (e) {
            if (e.type === 'click' || (e.type === 'keypress' && (e.which === 13 || e.which === 32))) {
                e.preventDefault();
                const stationUrl = $(this).data('station-url');
                const stationLogoUrl = $(this).data('station-logo');
                const stationName = $(this).data('station-name');
                playStation(stationUrl, stationLogoUrl, stationName);

                if (popupPlayerWindow && !popupPlayerWindow.closed) {
                    // If main player starts, consider closing or updating popup
                    // popupPlayerWindow.close();
                    // popupPlayerWindow = null;
                }
            }
        });
    }

    if (openPopupButton.length && typeof ycRadioPlayer !== 'undefined' && ycRadioPlayer.popupUrl) {
        openPopupButton.on('click', function () {
            pauseMainPlayer();

            let currentStationUrl = '';
            let currentStationLogo = '';
            let currentStationName = '';

            if (playerContainer.length) {
                currentStationUrl = playerContainer.attr('data-current-station-url') || '';
                currentStationLogo = playerContainer.attr('data-current-station-logo') || defaultLogo;
                currentStationName = playerContainer.attr('data-current-station-name') || 'Radio Player';
            }

            const popupUrl = new URL(ycRadioPlayer.popupUrl);
            if (currentStationUrl) popupUrl.searchParams.append('station_url', currentStationUrl);
            if (currentStationLogo) popupUrl.searchParams.append('station_logo', currentStationLogo);
            if (currentStationName) popupUrl.searchParams.append('station_name', currentStationName);

            // --- ADJUST POPUP WINDOW DIMENSIONS HERE ---
            const popupWidth = 600;  // As requested
            const popupHeight = 900; // As requested
            // --- END ADJUST ---

            const left = (screen.width / 2) - (popupWidth / 2);
            const top = (screen.height / 2) - (popupHeight / 2);
            const windowFeatures = `width=${popupWidth},height=${popupHeight},top=${top},left=${left},resizable=yes,scrollbars=yes,status=yes`;

            if (popupPlayerWindow && !popupPlayerWindow.closed) {
                popupPlayerWindow.close();
            }
            popupPlayerWindow = window.open(popupUrl.toString(), 'YCRadioPopupPlayer', windowFeatures);

        });
    } else {
        if (!openPopupButton.length) console.warn("YemCoders Radio Player: Popup button not found.");
        if (typeof ycRadioPlayer === 'undefined' || !ycRadioPlayer.popupUrl) {
            console.warn("YemCoders Radio Player: Popup URL not localized to script.");
        }
    }

    const audioElement = playerElement[0];
    const soundBarDiv = $('#player-container .sound-bar'); // More specific selector for main player's sound bar

    function updateMainPlayerSoundBarAnimation() {
        if (!audioElement || !soundBarDiv.length) return;
        if (audioElement.paused || audioElement.ended || audioElement.error) {
            soundBarDiv.addClass('paused'); // Assumes your style_v2.css has .sound-bar.paused
        } else {
            soundBarDiv.removeClass('paused');
        }
    }

    if (audioElement) {
        setTimeout(updateMainPlayerSoundBarAnimation, 100);
        audioElement.addEventListener('play', updateMainPlayerSoundBarAnimation);
        audioElement.addEventListener('playing', updateMainPlayerSoundBarAnimation);
        audioElement.addEventListener('pause', updateMainPlayerSoundBarAnimation);
        audioElement.addEventListener('ended', updateMainPlayerSoundBarAnimation);
        audioElement.addEventListener('error', updateMainPlayerSoundBarAnimation);
        audioElement.addEventListener('emptied', updateMainPlayerSoundBarAnimation);
        audioElement.addEventListener('loadstart', function () {
            if (soundBarDiv.length) soundBarDiv.addClass('paused');
        });
    }
});
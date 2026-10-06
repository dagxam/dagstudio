(() => {
  const roots = [...document.querySelectorAll('.ds-audio-player')];
  if (!roots.length) return;

  const formatTime = seconds => {
    if (!Number.isFinite(seconds) || seconds < 0) return '--:--';
    const total = Math.floor(seconds);
    const mins = Math.floor(total / 60);
    const secs = String(total % 60).padStart(2, '0');
    return mins + ':' + secs;
  };

  const setRangeFill = (input, percent) => {
    const safe = Math.max(0, Math.min(100, Number.isFinite(percent) ? percent : 0));
    input.style.setProperty('--range-progress', safe + '%');
  };

  roots.forEach(root => {
    const audio = root.querySelector('audio');
    if (!audio) return;

    const ui = document.createElement('div');
    ui.className = 'ds-player-ui';
    ui.innerHTML = [
      '<button class="ds-play-toggle" type="button" aria-label="Воспроизвести">',
        '<span class="ds-play-icon" aria-hidden="true"></span>',
      '</button>',
      '<div class="ds-player-main">',
        '<input class="ds-seek" type="range" min="0" max="100" step="0.05" value="0" aria-label="Позиция воспроизведения">',
        '<div class="ds-player-time"><span class="ds-current-time">0:00</span><span class="ds-duration">--:--</span></div>',
      '</div>',
      '<div class="ds-player-volume">',
        '<button class="ds-mute-toggle" type="button" aria-label="Выключить звук">',
          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4Zm12.5 3a4.5 4.5 0 0 0-2-3.74v7.48A4.5 4.5 0 0 0 16.5 12Zm0-8.25v2.1A7 7 0 0 1 20 12a7 7 0 0 1-3.5 6.15v2.1A9 9 0 0 0 22 12a9 9 0 0 0-5.5-8.25Z"/></svg>',
        '</button>',
        '<input class="ds-volume" type="range" min="0" max="1" step="0.01" value="1" aria-label="Громкость">',
      '</div>'
    ].join('');

    root.appendChild(ui);
    root.classList.add('is-enhanced');

    const playButton = ui.querySelector('.ds-play-toggle');
    const seek = ui.querySelector('.ds-seek');
    const current = ui.querySelector('.ds-current-time');
    const duration = ui.querySelector('.ds-duration');
    const muteButton = ui.querySelector('.ds-mute-toggle');
    const volume = ui.querySelector('.ds-volume');

    const syncPlayState = () => {
      const playing = !audio.paused && !audio.ended;
      root.classList.toggle('is-playing', playing);
      playButton.setAttribute('aria-label', playing ? 'Пауза' : 'Воспроизвести');
    };

    const syncTime = () => {
      const length = Number.isFinite(audio.duration) ? audio.duration : 0;
      const position = Number.isFinite(audio.currentTime) ? audio.currentTime : 0;
      current.textContent = formatTime(position);
      duration.textContent = length > 0 ? formatTime(length) : '--:--';
      const percent = length > 0 ? (position / length) * 100 : 0;
      seek.value = String(percent);
      setRangeFill(seek, percent);
    };

    const syncVolume = () => {
      const effective = audio.muted ? 0 : audio.volume;
      volume.value = String(effective);
      setRangeFill(volume, effective * 100);
      root.classList.toggle('is-muted', effective === 0);
      muteButton.setAttribute('aria-label', effective === 0 ? 'Включить звук' : 'Выключить звук');
    };

    playButton.addEventListener('click', () => {
      if (audio.paused || audio.ended) {
        audio.play().catch(() => {});
      } else {
        audio.pause();
      }
    });

    seek.addEventListener('input', () => {
      if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
      const percent = Number(seek.value);
      audio.currentTime = audio.duration * (percent / 100);
      setRangeFill(seek, percent);
    });

    volume.addEventListener('input', () => {
      audio.muted = false;
      audio.volume = Number(volume.value);
      syncVolume();
    });

    muteButton.addEventListener('click', () => {
      if (audio.muted || audio.volume === 0) {
        audio.muted = false;
        if (audio.volume === 0) audio.volume = 0.8;
      } else {
        audio.muted = true;
      }
      syncVolume();
    });

    audio.addEventListener('loadedmetadata', syncTime);
    audio.addEventListener('durationchange', syncTime);
    audio.addEventListener('timeupdate', syncTime);
    audio.addEventListener('volumechange', syncVolume);
    audio.addEventListener('play', () => {
      roots.forEach(otherRoot => {
        if (otherRoot === root) return;
        const otherAudio = otherRoot.querySelector('audio');
        if (otherAudio && !otherAudio.paused) otherAudio.pause();
      });
      syncPlayState();
    });
    audio.addEventListener('pause', syncPlayState);
    audio.addEventListener('ended', () => {
      audio.currentTime = 0;
      syncPlayState();
      syncTime();
    });

    audio.volume = Math.min(1, Math.max(0, audio.volume || 1));
    syncPlayState();
    syncTime();
    syncVolume();
  });
})();
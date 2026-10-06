(() => {
  const players = [...document.querySelectorAll('.public-player audio')];
  players.forEach(player => {
    player.addEventListener('play', () => {
      players.forEach(other => {
        if (other !== player && !other.paused) other.pause();
      });
    });
  });
})();
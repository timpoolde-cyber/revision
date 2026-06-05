// VisionControl - Echtzeit-Renderingfunktion für Status-Quadrate mit Countdown
window.renderPhaseSquares = function(phase, color, secondsRemaining) {
  const colorMap = {
    green: '#0d8659',
    orange: '#FF9529',
    red: '#FF3131',
    gray: '#696969'
  };

  const activeColor = colorMap[color] || '#eee';
  const inactiveColor = '#eeeeee';
  const activeText = '#ffffff';
  const inactiveText = '#cccccc';

  let displayedSeconds = secondsRemaining;

  // Formatiere Countdown: "14d" bis "1d", dann "23h" bis "1h"
  const formatCountdown = (seconds) => {
    if (seconds === null || seconds === undefined || seconds < 0) return '';

    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const mins = Math.floor((seconds % 3600) / 60);

    if (days > 0) return `${days}d`;
    if (hours > 0) return `${hours}h`;
    if (mins > 0) return `${mins}m`;
    return `${seconds}s`;
  };

  // HTML-Generierung: 6 Quadrate mit Vollfarbe (kein Gradient)
  const generateHTML = (countdownText = '') => {
    let html = '';
    for (let i = 0; i < 6; i++) {
      const isActive = i < phase;
      const bgColor = isActive ? activeColor : inactiveColor;
      const textColor = isActive ? activeText : inactiveText;

      // Countdown nur im ersten inaktiven Quadrat (i === phase)
      let content = String(i + 1);
      if (!isActive && countdownText && i === phase) {
        content = countdownText;
      }

      html += `<div class="status-square" style="background-color:${bgColor};color:${textColor};">${content}</div>`;
    }
    return html;
  };

  // Interval für sekündliche Countdown-Updates (nur wenn Countdown vorhanden und nicht gray)
  let intervalId = null;
  if (secondsRemaining !== null && secondsRemaining !== undefined && color !== 'gray') {
    intervalId = setInterval(() => {
      if (displayedSeconds > 0) {
        displayedSeconds--;
        const countdownText = formatCountdown(displayedSeconds);

        // Dispatch Event für DOM-Update
        document.dispatchEvent(new CustomEvent('visionControlCountdownUpdate', {
          detail: { phase, countdownText, displayedSeconds }
        }));
      }
    }, 1000);
  }

  return {
    html: generateHTML(formatCountdown(displayedSeconds)),
    intervalId: intervalId,
    currentSeconds: displayedSeconds
  };
};

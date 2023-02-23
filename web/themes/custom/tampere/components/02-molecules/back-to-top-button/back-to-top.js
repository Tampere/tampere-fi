/**
 * @file
 * A JavaScript file containing functionality for the back to top component.
 *
 */

Drupal.behaviors.backToTop = {
  attach(context) {
    const easeOutCubic = (time) => --time * time * time + 1; // eslint-disable-line
    const motionQuery = window.matchMedia('(prefers-reduced-motion)');
    const backToTopElements = document.querySelectorAll('.back-to-top', context);
    const bodyElem = document.querySelector('body', context);
    let animationRequest;
    let prefersReducedMotion = motionQuery.matches;

    // Adapted from https://gist.github.com/drwpow/17f34dc5043a31017f6bbc8485f0da3c
    const backToTop = () => {
      const scrollDuration = 600;
      const targetPositionY = 0;
      const startingPositionY = window.scrollY;
      const difference = targetPositionY - startingPositionY;
      let startTime;

      const step = (timestamp) => {
        if (prefersReducedMotion) {
          window.scrollTo(0, targetPositionY);
        } else {
          if (startTime === undefined) {
            startTime = timestamp;
          }

          const currentTime = timestamp;
          const progress = (currentTime - startTime) / scrollDuration;
          const amount = easeOutCubic(progress);

          window.scrollTo(0, startingPositionY + amount * difference);

          if (progress < 0.99) {
            animationRequest = window.requestAnimationFrame(step);
          } else {
            window.scrollTo(0, targetPositionY);
          }
        }
      };

      window.requestAnimationFrame(step);
    };

    const handleUserInitiatedScroll = () => {
      if (animationRequest) {
        window.cancelAnimationFrame(animationRequest);
      }
    };

    const handleKeyDown = (keyDownEvent) => {
      if (keyDownEvent.key === 'ArrowUp' || keyDownEvent.key === 'ArrowDown') {
        handleUserInitiatedScroll();
      }
    };

    const handleReduceMotionChanged = () => {
      if (motionQuery.matches) {
        prefersReducedMotion = true;
      } else {
        prefersReducedMotion = false;
      }
    };

    const switchFocus = () => {
      bodyElem.focus();
    };

    window.addEventListener('wheel', handleUserInitiatedScroll);
    window.addEventListener('touchmove', handleUserInitiatedScroll);
    window.addEventListener('keydown', handleKeyDown);

    if (backToTopElements) {
      backToTopElements.forEach(element => {
        element.addEventListener('click', backToTop);
        element.addEventListener('click', switchFocus);
      });
    }

    motionQuery.addEventListener('change', handleReduceMotionChanged);
  },
};

(function () {
    const script = document.currentScript;
    const beaconUrl = script.getAttribute('data-beacon-endpoint') || '';
    if (!beaconUrl) return;

    const data = {
        timestamp: Date.now(),
        userAgent: navigator.userAgent,
        url: location.href
    };

    navigator.sendBeacon(beaconUrl, JSON.stringify(data));
})();

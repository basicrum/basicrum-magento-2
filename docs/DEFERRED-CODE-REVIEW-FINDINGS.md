# Deferred code-review findings

These findings were raised independently by the Grok and Fable 5.1 reviews of
the Phase 1 implementation. They are intentionally deferred from the current
change and must remain visible for follow-up planning.

## CR-D-001: Dynamic Magento CSP policy for the Beacon Endpoint

**Status:** Deferred.

The storefront passes the configured Beacon Endpoint to Boomerang, but the
module does not currently add that store-scoped origin to Magento CSP
`connect-src` or `img-src` policy. Restrictive CSP pages, especially checkout
and payment flows, may therefore block beacon delivery.

Follow-up must determine the supported Magento CSP API across the declared
framework range, add the effective endpoint origin without broad wildcards,
cover both send-beacon/XHR and image fallbacks, and verify the result on the
disposable Magento baseline. The existing same-origin loader and
`SecureHtmlRenderer` handling do not resolve this collector-origin policy.

## CR-D-002: Full-page-cache invalidation after Basicrum configuration changes

**Status:** Deferred.

The cacheable footer embeds the effective endpoint, Brum Site ID, consent
choice, query-redaction flag, and wait configuration. The module's backend
models validate saved values but do not explicitly invalidate Magento
full-page cache, so cached pages may retain earlier monitoring behavior after
an administrator saves configuration.

Follow-up must first verify Magento core and Varnish behavior on the disposable
baseline, then implement correctly scoped invalidation for every storefront
setting if core config-save behavior is insufficient. Until then, the README's
upgrade guidance to clean configuration, layout, block HTML, full-page, CDN,
and optimizer caches remains the operational mitigation.

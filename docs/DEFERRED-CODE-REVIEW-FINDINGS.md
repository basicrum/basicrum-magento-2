# Deferred code-review findings

These findings were raised independently by the Grok and Fable 5.1 reviews of
the Phase 1 implementation. CR-D-001 was subsequently implemented by adapting
the compatible CSP work from upstream PR #13. CR-D-002 remains intentionally
deferred and visible for follow-up planning.

## CR-D-001: Dynamic Magento CSP policy for the Beacon Endpoint

**Status:** Implemented.

The storefront CSP collector now consumes the same validated effective runtime
configuration as rendering. While monitoring is active, it adds only the
normalized endpoint origin to `connect-src` and `img-src`, covering
send-beacon/XHR and image fallbacks without wildcards. Disabled, incomplete,
or invalid configuration adds nothing. The collector is registered in global
dependency injection alongside Magento's core collectors; area-level array
registration would replace them. An explicit frontend runtime guard keeps
Basicrum's contribution out of Admin, API, cron, and unset-area contexts.

Focused tests cover the inactive gate, production HTTPS normalization,
path/query exclusion, port retention, both directives, and the explicit
development HTTP exception. Native tests check core whitelist preservation,
an enforcing checkout response, and the absence of Basicrum's origin in Admin.
Execution results and baseline limitations are recorded in
`OPUS-REVIEW-FOLLOWUPS.md`; the pinned pre-release gate remains required.

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

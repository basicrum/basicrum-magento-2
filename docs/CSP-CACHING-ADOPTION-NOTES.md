# CSP, caching and testing: adoption notes

Recorded on 2026-09-23 after a read-only consultation with Opus 5.5 Max and
Grok 4.7 through their CLIs, followed by independent Codex verification.
Claude reported `claude-opus-5-5`; Grok reported `grok-4.7-build` in usage metadata.
The reviewed module commit was `c2d6241efc21c16b7ef5723eae4052807c137c41`.

**Status: future work only.** These are selected priorities, not implemented
features, passing tests, or authorization for a release. The user requested
that the decisions be retained for later. Production behavior is unchanged;
[CR-D-002](DEFERRED-CODE-REVIEW-FINDINGS.md#cr-d-002-full-page-cache-invalidation-after-basicrum-configuration-changes)
remains deferred.

## Selected priorities

| Priority | Proposed work | Reason | Estimated effort |
| --- | --- | --- | --- |
| 1 | Real Admin saves against warm full-page cache | Establish what visitors actually receive after privacy/configuration changes | Medium |
| 2 | CSP assertions on existing real cache-HIT responses | Verify the effective collector origin and Magento's core policy sources survive cache hits | Small |
| 3 | Clearer cache-operation and CSP guidance, including Admin copy | Explain cache refresh requirements and distinguish tested modes from unverified combinations | Small |

These should be test/documentation improvements first. Do not replace the
loader, consent model, native CSP renderer or collector as part of this work.

### 1. Real Admin saves against warm full-page cache

Use the guarded disposable Magento installation, with caching enabled and a
real Admin form submission. The current
[configuration-save harness](../tests/integration/config-save.php) tests
validation and inheritance inside rolled-back transactions, disables
configuration caching for that process, and invokes the save model rather
than the complete Admin controller path. It cannot prove storefront cache
refresh behavior.

Start with a bounded set of representative transitions:

- Immediate loading to required consent, including a store override returning
  to an inherited consent requirement.
- Enabled to disabled monitoring.
- A Beacon Endpoint change, checking both rendered configuration and CSP.

Acceptance criteria:

- Establish a genuine warm FPC HIT before saving; do not manufacture headers.
- Record the cache invalidation status, next response's HIT/MISS status,
  rendered configuration/loader, CSP and actual tracking behavior separately.
- Inspect behavior before and after Magento's normal Page Cache refresh.
  Do not clean the cache between the save and the pre-refresh observation.
- Use fresh browser contexts for post-save visits so a previous page's
  initialized Boomerang cannot obscure the result.
- Restore settings and clean test cache entries in a `finally` path. Keep the
  test serial and isolated from other native configuration tests.
- Do not describe one store-view test as proof of cross-store eviction or
  Varnish/CDN purge. Expand scope only when necessary for an intended fix.

A stale response confirms the practical impact of CR-D-002. It does not, by
itself, select an automatic cache-clearing implementation.

### 2. Check CSP on the HIT itself

The existing [storefront tests](../tests/integration/storefront.spec.js)
check CSP on an initial response, and separately require actual cache HITs
for reload and independent-visitor scenarios. Add policy assertions to those
HIT responses using the existing [CSP helpers](../tests/integration/csp.js).

Verify core sources remain present and the effective collector origin appears
in `connect-src` and `img-src`. Retain consent silence, single loading, beacon
identity and visitor-isolation checks. Report-only policy checks must remain
labelled report-only: they are not proof that a browser enforced the policy.

This is a small test-only change; no production CSP changes are indicated.

### 3. Explain operational requirements precisely

After the native observations above, improve README/integration guidance and
relevant Admin copy to explain:

- Saving configuration and marking a cache invalid are not the same as
  removing cached pages.
- Disabling monitoring or tightening consent can require a Page Cache refresh
  and any operator-managed CDN/optimizer purge before cached pages reflect
  the change.
- The current enforcing-CSP fixture is deliberately non-cacheable; the
  homepage FPC checks are separate and report-only.
- Existing pages already open in browsers are not refreshed by a cache purge.

Keep [verification documentation](QUALITY-AND-RELEASE-READINESS.md) explicit
about the baseline, executed checks and unsupported/unverified combinations.

## Source findings and an important review correction

Codex inspected relevant source copied read-only from the stopped disposable
Magento Open Source 2.4.7-p10 installation. The reviewers also inspected the
available 2.4.7 GA and/or 2.4.9-line quality components. Those component sets
must not be presented as native 2.4.7-p10 execution evidence.

On the inspected baseline:

- The Admin configuration-save postdispatch observer calls
  `Magento\PageCache\Observer\InvalidateCache`, which invokes
  `TypeList::invalidate('full_page')`. That method records an invalidation
  flag; `cleanType()` is a separate operation that removes cached data.
- `Magento\Config\Model\Config::save()` dispatches the section-change event
  with changed paths and scope information, but model-only invocation does
  not run the Admin controller's postdispatch observers.
- Built-in FPC stores the response in the result-rendering path. Magento adds
  CSP later at `controller_front_send_response_before`. A cache HIT bypasses
  normal rendering, so render-time nonce registration cannot be assumed to
  survive. Route-specific CSP selection also depends on the full action name,
  which requires attention when routing is bypassed on a HIT.
- Varnish caches the completed HTTP response, so its header/nonce lifecycle
  differs from built-in FPC. Cleaning local FPC does not prove a Varnish purge.

Grok initially inferred that storing response headers meant a built-in HIT
necessarily retained a matching CSP header and remained executable. Codex
challenged the event ordering; Grok withdrew that conclusion and its proposed
assertion requiring nonce reuse. Opus independently identified the ordering.
**Do not encode nonce reuse as an intended secure contract.**

These are source observations. No warm-cache Admin-save or cacheable
enforcing-CSP scenario was executed during this consultation.

## Later investigations and compatibility coverage

1. **Bounded cacheable enforcing-CSP diagnostic.** Observe real MISS/HIT and
   independent visitors, recording the effective policy mode, nonce/header
   relationship and script execution. Check enforcement on every response;
   falling back to report-only must not count as an enforcing pass. Use native
   configuration in an isolated environment, not rewritten headers/assets.
   Keep the existing non-cacheable fixture. A discovered platform limitation
   calls for an explicit support boundary, not an improvised nonce bypass.
2. **One additional native Magento/PHP baseline.** Select a currently supported
   combination when this work is scheduled. Start with a manually triggered
   lane if appropriate; component/static matrices do not replace native
   rendering, DI compilation, asset deployment and browser execution.
3. **Pinned Varnish lane.** Add before claiming Varnish verification. Require
   genuine MISS/HIT evidence, policy consistency, visitor isolation and actual
   purge behavior. This is a separate infrastructure investment, not equivalent
   to Redis-backed built-in caching. External CDN management remains distinct.

## Automatic cache clearing: disagreement and decision

Opus favored Magento's normal invalidation/manual-refresh convention and
warned about site-wide eviction, unaffected stores and external caches. Grok
favored a narrow native clean if the warm-cache test confirmed stale output.

**Decision: do not adopt automatic clearing yet.** First establish the real
Admin-save behavior and the intended guarantee. If automatic refresh is later
chosen, evaluate the smallest native solution for changed storefront settings,
including no-op saves, inheritance, inactive-to-active transitions, configured
Varnish, and CLI/import behavior. Do not assume per-store eviction exists, or
that a local `full_page` clean purges Varnish/CDNs. Do not add an unconditional
whole-cache flush or a new cache framework.

## Peer practices not selected

The useful lessons are native CSP integration, separation of shared HTML from
visitor state, and tests of actual rendered behavior. Basicrum already uses
`SecureHtmlRenderer`, a validated storefront-only endpoint collector and
current-page consent callbacks without its own persisted consent decision.

Do not borrow merely because a competitor has it:

- Customer-data/AJAX storage for Basicrum consent.
- HTML post-processing CSP helpers or hand-written nonce machinery.
- Broad wildcard allowlists or disabling CSP/FPC to make scripts run.
- `cacheable="false"` on normal storefront layouts.
- GTM plumbing, server-side tracking or unrelated visitor identity features.

An external bootstrap reading non-executable configuration could be examined
if strict CSP plus cached pages becomes a product requirement. It is not
selected here: it changes the loader integration contract and needs its own
cross-plugin behavior review.

## Consultation boundaries

No implementation or competitor test suite was run during the review. No
Magento service was started, configuration changed, or beacon transmitted.
The decisions are recommendations, not a new compatibility certification.
WordPress, Magento 1 and the shared parity ledger were left unchanged.

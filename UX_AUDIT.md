# Fedfe Buffet usability audit

Reviewed on 5 November 2026 after the first implementation. The original audit was read-only. The approved refinement is now implemented and verified in Chrome. Findings, screenshots, measurements, and source line references in the original audit describe the first implementation; they are not claims that every finding remains in the revised files.

## Approved refinement status

The staff food table, customer category headings and both category lists now follow the agreed restaurant category order, with food IDs ascending within each category. New categories appear after the nine standard categories. Only STAFF and MANAGER remain in the role selector, server-side validation, permission check, installation SQL and live database enum. There were no existing accounts using the removed role; existing IDs, usernames and password hashes were preserved. Chrome verification confirmed matching category orders, the two role choices and rejection of a forged unsupported role. The temporary manager used for verification was removed afterward. All four changed PHP files passed syntax checks.

### Availability-toggle regression audit

The first Ajax availability patch had two defects: the hidden `action` input shadowed the form's `action` property, causing a 404; the URL correction still omitted the form Content-Type header, so PHP received no POST fields and rejected the missing CSRF token with 403. The earlier isolated JavaScript check did not exercise PHP request parsing and was insufficient. The handler now uses `getAttribute("action")` and explicitly sends `application/x-www-form-urlencoded;charset=UTF-8`. Security-token validation remains enabled.

Verified through actual Chrome clicks against localhost/PHP using an isolated temporary STAFF account and food: four alternating availability changes returned 200, updated the row, retained scroll position without reloading, and persisted after reload. Invalid CSRF returned 403, unauthenticated access returned 401, and STAFF deletion returned 403; rejected requests left the food unchanged. No browser exceptions or error dialogs occurred. The other three Ajax POST call sites already specify their form encoding and do not use the shadowed `form.action` property. The exact temporary food/account were deleted afterward; existing menu records and active tables were not changed.

The latest approved correction keeps procedural PHP, ordinary for/while loops, PDO, and the existing SQL schema. It preserves service history and existing user data during refinement. There are nine operating tables. The fresh-install seed contains nine closed tables and three historical visits; the existing live table-10 record remains only because a historical visit references it. It is unavailable for new service. The seed does not describe the current live data.

| Area | Approved behavior | Verification status |
|---|---|---|
| Customer structure | White/blue menu, cart, and submitted-orders pages; compact header showing only `โต๊ะ N`; table-preserving navigation | Browser checks passed; included in the latest three-width screenshot review |
| Food display | Foods grouped under category headings; prices displayed only for paid extras; sold-out foods are labelled and cannot be ordered | Included in the latest browser and screenshot review |
| Inaccessible table | Centered icon and concise Thai message, with no employee entry link | Included in the latest browser and screenshot review |
| Page ownership | Each page contains its complete HTML boilerplate, stylesheet links and deferred scripts; imports use plain literal relative paths; header/footer functions render shared fragments | Fresh PHP language-server analysis passed |
| Draft persistence | Anonymous PHP session cart keyed by visit; retained across navigation, refresh, help, and rejected submissions | Browser checks passed |
| Quantity and notes | Minus/count/plus on foods and cart rows; zero removes; notes optional on cart page | Rapid edits, navigation, notes, empty cart, failed save/retry and invalid quantity checks passed |
| Browser isolation | Separate browsers/devices have separate carts; tabs in one browser for the same visit share its cart | Independent-browser and changed-draft review checks passed |
| Customer recovery | Staff can clear a mistaken bill request; closed visit history remains attached to its original visit | Resume-ordering and closed-history checks passed |
| Money review | Changed menu prices require review; checkout compares the reviewed amount with the current server total | Price-change review, pending-order rejection, 638-baht total and stale-total rejection passed |
| Staff operations | Fast XMLHttpRequest table opening, safer live updates, retained form values/edit context, deliberate correction while visit is open | Fast opening, stale notice without losing input, form recovery, status correction and AJAX authentication checks passed; edit-value restoration also reviewed |
| Staff layout | Nine operating table cards; wider guest inputs with adjacent opening buttons; add/edit summary icons and compact, aligned row actions | Included in the latest three-width screenshot review |
| Employee accounts | Manager-only prepared SQL DELETE; self-deletion rejected by the server and omitted from the UI; deleted accounts lose access on subsequent protected requests | Real deletion, self-delete rejection and deleted-account access revocation checked |
| Documentation | README, PLAN, three-page wireframes, and application architecture updated | Updated to match final implementation and observed results |

Fresh Intelephense analysis covered 27 PHP files with zero errors, zero warnings and zero informational diagnostics. The latest visual review covered 33 screens: 11 page/scenario states at widths of 390, 768 and 1440px. The compact food cards were additionally checked at 320, 390, 768 and 1440px. PHP and JavaScript syntax checks passed with no browser exceptions or console errors during the workflow.

Latest verification cleanup is complete: temporary employee accounts 6 and 7 and test visit 28 were removed by their recorded IDs. A read-only check confirmed no temporary accounts or test visit remain, original accounts 1–3 and active visits 26–27 remain, table 9 is closed, and historical visit 18 on table 10 remains. No existing passwords were changed.

## Earlier refinement verification — historical record

The following paragraphs retain the earlier refinement's verification and follow-up record. Their cleanup statements refer to that earlier pass, and their pending items describe the state at that time. They do not establish cleanup of the latest fixtures.

The earlier compact-menu review measured the first food at 155px on a 390px phone viewport. This measurement predates the latest grouped-category refinement.

The revised checks exercised work preservation, error recovery, context and displayed-versus-final amounts separately from the first version's functional tests. Test visits 21–23 on reserved table 10 and test food 45 were removed by exact IDs afterward. Other user records were preserved. PHP syntax checks passed for 27 files, both JavaScript syntax checks passed, and the rollback-only CLI smoke check passed. No JavaScript errors occurred in the browser workflow. Controls were also checked for clipping at 320, 390, 768 and 1280px.

Static backend review during refinement identified three follow-ups to verify before closing this audit: AJAX staff actions must return an authentication error rather than following a login redirect; failed edit forms must retain intentionally empty optional values; and a shared draft changed in another tab must be reviewed again instead of submitting quantities different from the visible cart. These were reported for implementation review. No database writes were performed by the documentation/review task. The CLI check gained pure visit-isolation/cart-count checks and passed PHP syntax validation; it was not executed during this task.

## Original audit conclusion — historical first implementation

The remainder of this document preserves the original read-only audit. Its findings, line references, screenshots and proposed corrections describe the first implementation and are retained as historical evidence.

The first application implemented the main transactions but did not provide a suitable customer ordering flow. Combining the menu, draft cart, order history, service calls, and billing into one page was a poor design choice. Functional browser tests passed without adequately checking draft preservation, navigation context, scrolling effort, and error recovery.

The main correction is three ordinary customer PHP pages, a persistent draft cart, visible quantity controls, and compact navigation. No framework, new database entities, or membership system is needed.

## Coverage and evidence

Reviewed the 35 project files: customer and staff pages, shared PHP, actions, AJAX endpoints, scripts, styles, SQL, diagrams/wireframes, documentation, smoke check, and placeholder assets. Checked the three user screenshots. Inspected the current customer page in local Chrome using GET navigation and local browser interactions only; no service/order submissions or database changes.

At 390 x 844 viewport size on the current table-1 page:

| Element | Top position |
|---|---:|
| Customer heading | 97 px |
| Food list | 562 px |
| Cart | 7,524 px |
| Order history | 7,814 px |

The 43-food menu itself occupied approximately 6,937 px. These measurements depend on the current contents and viewport, but establish why the mobile flow is inconvenient. The cart begins almost nine viewport heights down the page. The desktop sidebar also grows by a full note form for each selected food.

Chrome also confirmed that adding one draft item and clicking the restaurant name navigates to `/fedfebuffet/` without the table query. The customer application is then absent from that destination.

## Customer findings

| Priority | Finding | Evidence | Proposed correction |
|---|---|---|---|
| P1 | Draft cart disappears during ordinary service actions | Help form submits no draft items; customer action redirects; customer.js recreates an empty object. Restoration exists only for failed order submissions. | Persist quantities and notes for the current browser and visit. Calling staff should acknowledge without navigating away. |
| P1 | Brand navigation loses the table | includes/header.php:13 generates a customer link without `?table=N`. | Compact customer header showing only the table label; all navigation preserves the table and current visit. |
| P1 | Menu, cart and history are combined into an impractically long page | index.php:96,123,146; css/customer.css switches to columns only at 1024 px. | Menu page, dedicated cart page, and dedicated submitted-orders page. Always reachable navigation. |
| P1 | Adding food gives weak feedback where the customer is looking | Add confirmation appears in the offscreen cart. Food cards never display their selected quantities. | Inline minus/count/plus controls, zero removes a draft item, visible cart count. |
| P2 | Header and controls use excessive screen space | index.php:57-85 adds slogan, headcount, buffet price, visit code, explanation, and four equally prominent actions before food. | Show the table label and compact task navigation. Remove customer slogans, internal visit codes, headcount, routine technical explanations, and the requested tagline. Keep paid-extra prices attached to their foods. |
| P2 | Quantity changes require switching attention to a distant form | Menu offers only add; decrement/remove is in the cart sidebar. | The same quantity controls on food cards and cart rows. Respect maximum quantities visibly. |
| P2 | Repetitive notes and explanatory text make each cart row large | customer.js constructs a heading, quantity field, remove button, textarea, and counter for every row. | Cart page with compact rows and optional per-item notes; do not show note forms while browsing the menu. |
| P2 | Chargeable-food review omits individual prices | Cart shows a count of paid portions, without unit extra prices. | PHP renders the extra price beside each applicable cart item. Final bill calculation remains server-side. |
| P2 | Bill request strands selected but unsent items | Request immediately freezes ordering; confirmation does not identify a nonempty draft cart. | Put bill request on the submitted-orders/service screen and explicitly handle a nonempty draft before freezing the visit. |
| P2 | Bill request has no recovery action | request_bill is set until checkout; staff have no resume-ordering action. customer.js also only disables ordering, with no restore path. | Staff can cancel a mistaken bill request; polling must restore ordering controls when the flag clears. |
| P2 | Availability recovery identifies no specific failed food | Server gives a generic unavailable-food message; restored cards can retain the old available state. | Identify affected foods, mark their cart rows unavailable, retain other selections and notes, allow removal/correction. |
| P2 | Price can change between browsing and submission without acknowledgment | Customer action captures current MENU.price without comparing the amount last displayed. | On submission, compare the displayed extra price with the server price and return the cart for review if changed. Browser prices remain untrusted. |
| P2 | End-of-visit status removes order history | customer-status.php:8 replaces all orders with a closed-visit message. | The history page can show a clear completed state and the already-submitted visit history, without attaching it to a new group. |
| P3 | Category navigation disappears while scrolling and repeats labels on every card | Top select, then full menu list. | A compact reachable category control; remove repeated labels that add little orientation. Search is secondary to fixing the primary flow. |
| P3 | All controls depend on text-only rectangles | Header service/navigation buttons use the same treatment. | Small consistent icons beside short Thai labels for menu, cart, orders, help, back and removal. Native plus/minus symbols; no icon framework required. |

The discarded-draft problem is more serious than the existing warning that refresh loses unsent food. A customer should not need to understand browser reload behavior to safely call an employee.

## Employee findings

| Priority | Finding | Evidence | Proposed correction |
|---|---|---|---|
| P1 | Cashier can confirm one displayed amount while checkout saves a different amount | bill.php:64 sends only table/visit; actions/table.php:40 recalculates before closing. Concurrent guest/order changes can alter the amount. | Submit the reviewed total as an expected value, compare against server calculation inside the transaction, and require review again when changed. |
| P2 | Order polling replaces actionable buttons every five seconds | script/staff.js:18 unconditionally replaces the section. | Replace only when returned content changes; defer replacement while a control is focused or an action is in progress. |
| P2 | Live alerts coexist with stale table cards | staff/tables.php polls alerts but renders cards only on page load. | Make freshness clear and provide a targeted refresh; update read-only state without overwriting a guest field being edited. |
| P2 | Validation failure loses entered values and edit context | Menu/account actions redirect to the bare list on failure. | Retain non-password values and record ID for redisplay after the error. |
| P2 | A mistaken served/cancelled status cannot be corrected while the visit is still open | actions/order.php only accepts transitions from PREPARING. This follows the original plan's restriction but is awkward for human mistakes. | Consider a deliberate staff correction before payment, with the bill recalculated and reviewed. Keep closed visits immutable. |
| P2 | Routine operations reset scroll/context | Open, save, acknowledge, availability and status forms redirect to page top. | Keep the relevant table/item visible after success, or use a small XMLHttpRequest update for repeated operational actions. |
| P3 | Open-table cards expose controls even when unnecessary | Every open card shows guest editing and acknowledgment even with no active help request. | Show current guests as text; make editing deliberate; acknowledgment appears only for a real request. |
| P3 | Large add forms precede the main management lists | staff/menu.php:29; staff/employees.php:30. | Keep the list primary, with an explicit add action or a collapsed native form. Open the form when editing. |
| P3 | Raw role codes appear in the employee list | staff/employees.php originally rendered raw role codes directly. | Display Thai role labels consistently; the current system has only STAFF and MANAGER. |

Opening a table currently follows form POST -> PHP validation/write -> HTTP 303 -> fresh GET. The reload is normal for standard HTML/PHP forms and helps prevent accidental resubmission. It is not itself a server bug. Its disruptive scroll reset and stale surrounding state are the usability issues. AJAX can improve frequently repeated actions without changing the backend business rules.

## Reference checked after the user feedback

The first implementation was not based on a real restaurant ordering template. The custom wireframes repeated the same combined-page assumption and did not validate it.

The official Wongnai Mobile Order guide documents separate browsing, cart review, and submitted-order tasks; visible quantities; compact service/history icons; and a bottom cart entry. It provides a suitable behavioral reference for a smaller project adaptation:

- [Wongnai Mobile Order: scan and order at a table](https://help.wongnai.com/mobile-order/ordering/scan-order-at-table/)
- [Wongnai POS: send an order](https://help.wongnai.com/pos-staff/orders/send-order/)

These are references for task flow, not a requirement to copy their payments, random customer names, memberships, modifiers, promotions, or full POS feature set.

## Proposed customer structure

```text
index.php?table=1
    Compact table header, categories, food cards, minus/count/plus

cart.php?table=1
    Selected foods, quantity adjustment, optional notes, confirm order

orders.php?table=1
    Submitted orders and live states, service actions, bill request

Shared compact navigation
    เมนู | ตะกร้า (count) | รายการที่สั่ง
```

All three pages retain the active visit check and preserve table context. A closed old visit must not silently attach its draft to a new group.

The existing anonymous PHP session can hold a draft keyed by visit ID for this browser. That does not introduce customer accounts. PHP can render the cart with ordinary loops; small XMLHttpRequest handlers can save quantity/note changes without reloading. No JSON feature, classes, routing framework, or frontend dependency is necessary. JavaScript loops remain ordinary indexed for loops; PHP remains procedural with for/while.

Icons can be local SVG assets with Thai labels. They should clarify specific actions rather than decorate every element.

## What to retain

- Existing PDO queries, database structure, table/visit/order separation.
- Captured line charges, server-side bill calculation, cancelled-order exclusion.
- Employee permissions, password hashing, form tokens, duplicate-order protection.
- Transactions and stale-visit checks.
- Simple report scope and image filenames/placeholders.

The UI correction adds two customer pages but can remove the sidebar layout and much of the JavaScript that constructs cart forms. More files do not automatically mean more complex code; each page should have one clear task.

## Recommended correction order

1. Review one compact menu/cart/orders wireframe against real phone tasks.
2. Implement persistent draft state and safe table-preserving navigation.
3. Split customer pages and add visible quantity controls/compact navigation.
4. Improve bill-request recovery and price/checkout review safeguards.
5. Fix staff polling, stale state, form-error recovery and repetitive controls.
6. Recheck real tasks: browse several categories, select multiple foods, edit notes, navigate back, call staff, refresh, submit, follow status, request bill and close. Verify preservation of work and effort required, as well as successful database writes.

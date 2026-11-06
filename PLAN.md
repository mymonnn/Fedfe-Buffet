# Fedfe Buffet implementation and usability refinement plan

Prepared and approved on 5 November 2026 for the submission described as due tomorrow. The first implementation was built and functionally tested, then reviewed for usability. The approved refinement replaces the combined customer page with separate menu, cart, and submitted-order pages. Historical test results and the current verification status are distinguished in README.md and UX_AUDIT.md.

Coding constraint confirmed after planning: no PHP `foreach` and no JavaScript `.forEach()`. Use PDO fetch/while templates and ordinary indexed for loops or familiar JavaScript for/of loops.

The intended result is a small, explainable PHP buffet application running at `http://localhost/fedfebuffet/`. Customers order through a table link without an account. Employees manage tables and orders. Managers manage menus and employee accounts. The code should resemble the supplied course exercises.

## 1. What was studied

All 69 files in the context directory were covered:

- Seven project documents: both requirement documents, promotion document, database diagram, SQL seed, menu/image manifest, and rubric.
- Fifty exercise text/code/data files, including the complete JSON datasets.
- Twelve exercise image assets, inventoried and visually inspected together.

The SQL was read structurally and all rows were parsed and checked programmatically. Its data includes 10 tables, 43 menu items, 8 employees, 60 members, 976 visits, 358 visit/member links, 3,499 orders, and 14,460 order lines.

The exercises already use PDO, prepared statements, ordinary PHP/HTML templates, joins, grouped reports, GET/POST forms, XMLHttpRequest, fetch, and simple DOM manipulation. They do not establish a framework, OOP architecture, or build-tool workflow. We will follow the procedural approach and add the few native PHP mechanisms needed for authentication and safe multi-table writes.

The working directory was empty at initial review. Local checks confirmed PHP 8.2.12, PDO MySQL, MariaDB 10.4.32, Apache on port 80, MySQL on port 3306, and successful database access with `root` and an empty password. The application database `fedfebuffet` was subsequently created. The original `buffet` database remains separate. During refinement, preserve all current data and active visits; do not reimport the seed or reset the database.

## 2. Agreed scope and proposed defaults

The user explicitly confirmed live order updates and skipping a separate JSON feature unless the system needs one. XMLHttpRequest can return ordinary HTML, so JSON is unnecessary for the proposed implementation. Basic rubric criterion 7 will intentionally remain uncovered.

### Included

- Anonymous customer access through `?table=1`, provided that table is open.
- All 43 foods and 9 categories from the supplied menu.
- Three customer pages: food menu, cart review, and submitted orders/service.
- Visible minus/count/plus controls on foods and cart rows, optional notes on the cart page, and repeated orders during one visit.
- A draft cart stored in the anonymous PHP session by visit ID, surviving navigation, refresh, and help calls.
- A white/blue interface with a compact customer header showing only the table label and reachable task navigation.
- Live order status through XMLHttpRequest and PHP-generated HTML fragments.
- Calling an employee and requesting the bill.
- Employee login, logout, and access checks.
- Fast table opening through XMLHttpRequest, deliberate guest-count editing, help acknowledgment, reversible bill requests, and checkout with a reviewed-total check.
- One shared order screen for serving and kitchen staff.
- Whole-order states: preparing, served, or cancelled.
- Menu availability controls for staff; menu create/edit/delete for managers.
- Straightforward employee account management for managers, implemented after the core service flow.
- One manager-only daily sales table, reusing the kind of grouped query already present in the exercises. This is a small basic-rubric report, not a dashboard.
- Responsive customer and employee pages, with normal block layout and HTML tables.
- Placeholder images and the exact expected menu filenames.
- Short setup instructions, real page wireframes, and a system architecture diagram.

### Excluded

- Customer registration, login, member lookup, member points, and membership management.
- All promotions, including the group discount that does not require membership.
- JSON menu import, separate JSON files/endpoints, and rubric criterion 7.
- Graphs, dashboards, report filters, pagination, ingredient inventory, reservations, and online payment.
- Separate serving and kitchen applications, automatic QR generation, WebSockets, frameworks, Composer, and npm.

### Document differences to resolve explicitly

| Difference | Proposed choice |
|---|---|
| Requirement HTML hides unavailable food; requirement DOCX shows it marked sold out | Show a clear sold-out label and disable ordering. Deleted food is hidden. This follows the DOCX behavior and keeps availability understandable. |
| Both requirements mention a menu description, but the database lacks a column | Add one optional `MENU.description` text column in the project copy of the schema and diagram. Keep the existing entities and relationships. |
| The promotion document adds birthday, points, and group discounts | Omit all three in this version. New bills use the undiscounted formula. |
| HTML specifies JSON menu import | Omit it following the user's clarification. Seed the menu through SQL. |
| Diagram uses loose integer/date types and a boolean birthday-use field | Use the SQL's existing DECIMAL, DATETIME, and DATE definitions as authoritative. |
| Order wording varies slightly between the documents | Store the existing `PREPARING`, `SERVED`, `CANCELLED` values; use consistent Thai labels in the UI. |
| Requirements do not say whether ordering continues after a bill request | Freeze new orders after a bill request; staff can clear a mistaken request. Handle an unsent cart explicitly first. Employees resolve pending orders before checkout. |
| Account CRUD is marked secondary in the HTML | Include a small account page after the complete ordering and checkout flow works. |

These are proposed defaults for alignment, not claims that the source documents all say the same thing.

## 3. Database approach

For a fresh installation, create the application database `fedfebuffet` through the supplied SQL. The current machine already has it. Leave `buffet`, the original context SQL, the project schema, historical sample data, and current visits untouched during the usability refinement. No schema change or database reset is part of this correction.

Keep the eight designed entities, their existing field names, and their relationships. Use six in this version. Leave `MEMBER` and `SESSION_MEMBER` empty and unused; retaining their definitions preserves the designed architecture without implementing membership.

| Entity | Purpose in this version |
|---|---|
| `TABLE` | Physical table number and open/closed flag |
| `SESSION` | One group's visit, guest count, captured buffet price, requests, and final bill |
| `ORDER` | One submitted batch of food within a visit |
| `ITEM` | Food, quantity, note, and captured line charge within an order |
| `MENU` | Food name, category, extra price, image, availability, deletion flag, and optional description |
| `EMPLOYEE` | Employee credentials, names, contact details, role, and account management |
| `MEMBER`, `SESSION_MEMBER` | Retained definitions; no application features or sample member data |

```text
Physical table
  -> many visits over time
      -> many submitted orders
          -> many order items
              -> one menu record per item

Employee account -> authorizes the employee/manager screens
```

Keep SQL backticks around the existing table names, especially `TABLE` and `ORDER`. The name `SESSION` in this database means a dining visit. It is separate from a PHP login session.

### Seed data

Derive one small importable `database/fedfebuffet.sql` from the supplied schema. Include nine operating tables, the exact 43 menu items, a manager, a staff member, and a few coherent completed visits for the report. Preserve the existing live table-10 record only for its historical visit; do not expose it for new service.

Do not import the large historical dataset into the application database. It contains 148 discounted completed visits, which would be confusing in a version without promotions. Preserve it as reference in the context directory.

Use only `STAFF` and `MANAGER`. Staff use operational screens; managers also manage foods and accounts and view sales. The database role definition and server-side validation accept only these two roles.

Employee creation requires username, password, first name, last name, email, and role. Optional metadata can remain blank where allowed; registration and hire dates can default to today. Require unique email as the supplied schema does. Do not impose numeric national-ID validation on the original synthetic `TEST...` examples.

### Billing rules

- Buffet price is 299.00 baht per guest, captured when the visit opens.
- Included foods have a menu extra price of zero.
- Six desserts cost extra: vanilla/chocolate/strawberry ice cream 20 each, green tea ice cream 25, fruit bingsu 89, and mochi ice cream 45.
- `MENU.price` is the current unit extra price.
- `ITEM.price` is already the complete line total captured when ordering: quantity times the current menu extra price.
- Never multiply `ITEM.price` by quantity again during billing.
- Checkout total is guest count times captured buffet price, plus the sum of line totals on served orders.
- Cancelled orders contribute nothing. Pending orders must be served or cancelled before payment is finalized.
- Save the final amount in `SESSION.total_price`. Historical bill totals come from that saved amount.
- Use the database's DECIMAL fields and exact money arithmetic. Prices supplied by a browser are never authoritative.
- When a menu price differs from the price last reviewed, return the draft for an explicit price review rather than silently increasing the charge.
- At payment confirmation, compare the expected reviewed total with a fresh server calculation inside the existing transaction. If it differs, show the current bill for review; do not silently finalize another amount.

Example: two guests plus two 20-baht ice creams cost `2 x 299 + 40 = 638` baht. Cancelling an 89-baht bingsu order adds nothing.

Daily sales must aggregate closed visits directly, once per visit. Joining their total amounts to multiple order lines would accidentally multiply revenue.

## 4. Proposed file structure

This lists the application's agreed file structure. A small CLI billing check also lives in tests/smoke.php.

```text
fedfebuffet/
  index.php                       Customer menu and minus/count/plus controls
  cart.php                        Persistent draft, quantity/optional notes, order confirmation
  orders.php                      Submitted orders, live status, help, and bill request
  login.php                       Employee login form and login processing
  logout.php                      POST logout
  connection.php                  PDO connection to fedfebuffet
  functions.php                   A few shared validation, escaping, visit and bill helpers

  staff/
    tables.php                    Table opening, guests, requests, checkout links
    orders.php                    Shared employee order queue
    bill.php                      Bill review and payment confirmation
    menu.php                      Food list, availability, and manager add/edit form
    employees.php                 Manager account list and add/edit form
    report.php                    Manager daily sales table

  includes/
    auth.php                      Employee authentication and permission checks
    customer.php                  Shared table/visit lookup and visit-keyed draft context
    table-card.php                One table's markup reused by staff page and XMLHttpRequest
    header.php                    Shared page head, stylesheet links, navigation
    footer.php                    Shared footer and deferred script links

  actions/
    customer.php                  Save draft, place order, call employee, request bill
    table.php                     Open table, change guests, clear help/bill request, checkout
    order.php                     Mark whole order served or cancelled
    menu.php                      Save food, change availability, delete food
    employee.php                  Save account, change password, delete account

  ajax/
    customer-status.php           Current visit's orders and service status as HTML
    staff-status.php              Queue or read-only request alerts as HTML

  css/
    style.css                     Common typography, forms, buttons, tables, status labels
    customer.css                  Customer layout with tablet/desktop media queries
    staff.css                     Employee layout with tablet/desktop media queries

  script/
    customer.js                   Quantity/note saves, category controls, status polling
    staff.js                      Fast table opening, safe polling, simple confirmations

  images/
    placeholder.svg               One neutral missing-image placeholder
    menu/                         Partner supplies the 43 named JPG files here

  database/
    fedfebuffet.sql                Project schema and small demonstration dataset

  docs/
    wireframes.html               Actual sketches of customer and employee screens
    system-architecture.mmd       Browser -> PHP -> MariaDB diagram
    database-architecture.mmd     Project copy of the ERD with the small corrections

  PLAN.md                         This plan
  README.md                       Setup, credentials, demonstration, known scope, rubric map
```

Use the singular `script` directory requested by the user. Keep three CSS files with media queries instead of duplicating every style into separate phone/tablet/desktop files.

The dedicated cart and submitted-order pages keep each customer task short. No separate category, new-food, edit-food, new-employee, or edit-employee page is necessary. Menu and employee pages each keep their list primary, with an add/edit form opened deliberately.

## 5. How the files connect

The usual flow is `page -> form -> named action -> prepared SQL -> redirect back to page`.

- `connection.php` only opens the PDO connection; it does not render HTML.
- `functions.php` stays small. It does not become a generic data-access layer or CRUD engine.
- Rendering pages load their needed includes and run readable SELECT queries before the HTML.
- Normal form writes use POST and redirect after success. Small draft/service and frequent staff actions may return an HTML fragment through XMLHttpRequest to preserve context. No JSON feature or generic action framework is needed.
- Every state-changing POST validates a form/CSRF token, including employee actions and logout. GET requests do not change stored data.
- AJAX files return small HTML sections. JavaScript inserts them into a dedicated container.
- `includes/auth.php` protects every employee page, employee action, and employee AJAX request. Manager restrictions are checked on the server, including direct URLs.
- Protected AJAX requests return an appropriate error status when login expires, so a login page is not inserted into an order container.
- Every rendering page contains its complete HTML document, stylesheet links and deferred script directly. Shared header/footer files contain small explicitly called rendering functions only.
- Use literal relative includes from each entry page. Shared customer/auth functions receive arguments and return data instead of creating hidden page variables.

A staff page can start with familiar includes:

```php
<?php
require_once "../connection.php";
require_once "../functions.php";
require_once "../includes/auth.php";
$employee = require_employee($pdo);

// Page-specific SELECT queries and variables.
?>
```

Then use ordinary semantic HTML, short PHP loops, and escaped values. PDO statements remain explicit and close to the page/action that uses them.

## 6. Customer behavior

The customer entry URL is `http://localhost/fedfebuffet/?table=1`. A missing, invalid, or closed table shows a clear contact-staff message. It does not silently select a table.

The page loads the 43 menu entries through PHP. Category filtering can show/hide already-rendered foods through plain JavaScript; a separate AJAX menu endpoint is unnecessary for this small menu.

The anonymous PHP session holds a small draft keyed by visit ID, containing food IDs, quantities, notes, and the price-review context needed by submission. This draft is separate from database orders. Navigation, refresh, category changes, and calling staff preserve it. Different browsers/devices have separate drafts; tabs in the same browser for the same visit share the draft. Never describe those tabs as independent carts.

`index.php?table=N` is the food menu. `cart.php?table=N` reviews quantities, optional notes, and food-specific extra prices, then confirms the order. `orders.php?table=N` shows submitted orders, live status, help, and bill request. All navigation retains table/visit context. The header is just the table label; task navigation stays compact and reachable. Do not repeat slogans, internal visit codes, guest counts, or buffet explanations above the food list.

PHP renders the cart with ordinary for/while templates. Small XMLHttpRequest POSTs save quantity and note changes without building an entire cart form in JavaScript. Show quantity zero clearly as unselected; minus to zero removes an item. Keep requests ordered and await outstanding saves before navigation/submission so rapid taps cannot lose the final quantity or note. Show saving/failure status where relevant. Final order submission still validates current database values and clears the draft only after a successful commit.

Every customer POST carries the physical table number and the specific visit ID originally shown on the page. The server verifies that the table is still open and that this is still its active visit. An old tab must never submit into a new group's visit automatically. The same visit check applies to help and bill requests and to status polling.

Use positive integer quantities with a modest documented maximum, bounded notes, a nonempty cart, and available/nondeleted food. Start with quantities 1-99 and notes up to 200 characters. Validate these limits on both sides. Reload prices and availability from the database during submission.

Create the order and its items together in a short transaction. Disable repeated clicking and use a small one-use submission token to prevent duplicate orders from a repeated POST. Keep tokens per rendered form and visit, so another tab does not overwrite the first tab's token. Consume a token only after a successful commit.

If validation, availability, or price checks reject an order, keep the visit's existing session draft and return to the cart with a specific explanation. Identify affected foods and preserve the other quantities/notes. Never restore a previous group's draft into a new visit. Customer service actions must not navigate away and erase work.

Submitted-order status polls approximately every five seconds with XMLHttpRequest. PHP returns escaped HTML. Stop writes when the visit ends, retain the already-submitted history for that visit, and do not attach it to a newly opened group. Network failure leaves the last visible result intact and permits retry. Clearing a mistaken bill request must restore ordering controls.

## 7. Employee behavior and transitions

### Login and permissions

Use `password_hash()` when creating/changing passwords and `password_verify()` when logging in. Reject missing/deleted accounts. Retain the existing `is_deactivated` login check for compatibility with legacy records. Regenerate the PHP session ID on successful login.

Store the employee identity in `$_SESSION`. Load the current account/role on protected requests so account deletion revokes access and permission changes take effect on the next request. Staff may manage service and availability. Managers additionally create/edit/delete foods, manage accounts, and view the report.

Logout is POST. Clear the stored identity, expire the session cookie, and destroy the session. Retrieve the ID meaningfully with `session_id()` in the lifecycle; never print it on operational pages.

### Physical tables and visits

`TABLE.is_available = TRUE` means open for customer ordering, not vacant. An active visit has `SESSION.completed_at IS NULL`.

Opening a table creates one visit with the guest count, buffet price, and creation time, then marks the table open. A short transaction checks and locks the table so two clicks cannot create two active visits. Use a small XMLHttpRequest response to update the affected table without jumping to the page top. Guest count is editable deliberately while the visit is open; a later change must invalidate an already-reviewed payment amount.

Generate readable unique visit/order numbers while holding the same table/visit lock, backed by the existing unique keys. Do not use an unprotected global `COUNT()+1` or `MAX()+1`.

Table screens display help and bill-request alerts. Keep read-only state current without overwriting a guest-count field being edited. Employees acknowledge actual help requests to clear their flag. Mark freshness clearly and keep the relevant table visible after repeated actions.

### Orders

The shared queue is chronological and shows order number, table, time, food quantities, and notes. It polls the staff HTML endpoint. Serving and kitchen staff use the same screen.

Normal transitions are `PREPARING -> SERVED` or `PREPARING -> CANCELLED`. Record completion time. A manager can deliberately reset a mistaken served/cancelled status to preparing while the visit remains open, then staff choose the correct terminal status. The bill is recalculated and reviewed; closed visits remain immutable. Polling must not replace a focused/actionable button underneath an employee; update changed content only and defer disruptive replacement while it is being used.

### Checkout

Bill request belongs on the submitted-orders/service page. If this browser has an unsent draft, require the customer to send or deliberately remove it first. The request freezes new orders, but staff can clear a mistaken request and resume the same visit. The bill page shows guests, captured buffet price, extra-charge lines, cancelled exclusions, and the server-calculated total. It identifies unresolved preparing orders.

Employees must serve or cancel pending orders before confirming payment. Actual payment happens at the counter. Confirmation compares the reviewed total with the newly calculated amount. A difference returns the employee to review instead of silently accepting a changed amount. A matching confirmation saves the total, records completion, clears request flags, and marks the table closed in one transaction.

Ordering, order transitions, guest edits, and checkout use consistent visit/table locking. This avoids a late order or guest change slipping into a finalized bill.

Reopening the physical table creates a new visit and an empty order history for the new group.

### Menu and accounts

Staff can mark food available/sold out. Managers additionally create/edit food and mark it deleted through `is_deleted`, preserving old order references and captured charges.

Account deletion is a prepared SQL DELETE. Managers confirm the target account, and self-deletion/self-demotion is prohibited. Existing legacy fields are retained for compatibility rather than migrating user data. Blank password on editing means keep the existing password.

Retain non-password form values and the record ID after validation/duplicate-value errors. Return to the same edit/add context rather than discarding a completed form. Keep Thai role labels and routine controls consistent.

## 8. Cookies and PHP sessions in plain language

A cookie is a small value kept by the browser. A PHP session keeps data on the server. PHP normally connects the two using a session-ID cookie.

For employees this means: log in once, PHP remembers the employee ID on the server, and the browser sends the identifying cookie on later requests. Logout removes the remembered identity and expires the cookie. There is no need for a custom remember-me feature.

Customers do not need accounts or an authenticated identity. Table and visit context comes from the URL and validated form fields, not a global `$_SESSION['table']` that would conflict between pages. An anonymous PHP session holds the visit-keyed draft plus form-protection/submission tokens. This adds no membership feature or database entity.

The database `SESSION` entity records the group's meal. A PHP session remembers browser state. These two uses of the word session are independent.

## 9. Styling and image contract

Use `header`, `nav`, `main`, `section`, `article`, `form`, and `footer` naturally. Use tables for employee records and reports. Use normal block flow and a modest inline-block menu arrangement; no Flexbox or Grid is needed for the basic rubric.

Phone styling is the default. Add clear tablet and desktop media queries around 768px and 1024px. Employee tables can scroll horizontally on narrow screens. Aim for readable labels, large touch targets, visible focus states, and distinct status text as well as colors.

Use Thai customer/employee text to match the documents and menu, with English code/file names. Keep the visual treatment simple and consistent.

Use a white/blue palette for the revised customer pages. The top header shows only `โต๊ะ N`. Use a compact menu/cart/orders navigation, local small icons with Thai labels where helpful, and visible native minus/plus controls. Optional note fields belong in cart review, not on every menu card. Avoid repeating descriptions that do not help the current task.

Keep every filename in the supplied image manifest. For example, the partner's physical file belongs at `C:\xampp\htdocs\fedfebuffet\images\menu\sliced-beef.jpg`.

Build the photo URL from the project base and menu ID, such as `images/menu/1.jpg`. The supplied photos map to menu IDs 1–43. Managers can upload a JPEG when adding or editing a dish, and the app uses the dish ID as the filename. Show the bundled placeholder if a photo is missing or fails to load.

## 10. Basic rubric coverage

| # | Planned evidence |
|---|---|
| 1 | Actual customer/employee wireframes plus browser/PHP/database system diagram; ERD supplied separately |
| 2 | Natural use of type, universal, class, ID, pseudo-class, pseudo-element, and attribute selectors |
| 3 | Built-in validation on at least three distinct real fields, such as guest count, food name, and extra price |
| 4 | Semantic page layouts through shared page markup |
| 5 | Mobile-first layouts with at least two screen sizes |
| 6 | Two non-AJAX `getElementById(...).style` interactions, such as cart visibility and note-counter warning color |
| 7 | Deliberately omitted following the user's clarification |
| 8 | Database connection and menu create/read/update/delete; deletion preserves history using the deletion flag |
| 9 | Employee cookie/PHP-session login lifecycle, ID retrieval, variable removal, and logout |
| 10 | XMLHttpRequest with ordinary HTML `responseText` for live statuses |
| 11 | One plain grouped daily sales HTML table |

The rubric has no numerical point weights in the supplied file. Do not claim a guaranteed score or that all basic rows are covered. JSON is row 7, Ajax is row 10, and the plain report is row 11. Graphs and dashboards are not required for basic coverage.

## 11. Approved refinement order

1. Preserve current schema/data/active visits. Review the compact three-page customer sketches.
2. Store the draft in the existing PHP session by visit ID and add small ordered quantity/note saves.
3. Split menu, cart, and submitted orders; add white/blue styling, compact navigation, and visible quantities.
4. Add bill-request recovery and explicit menu-price/payment-total review guards.
5. Improve fast table opening, live staff state, focused controls, and form-error recovery.
6. Check real tasks and rejected flows without altering a user's active visit. Use a separate known closed table for transactional checks, then roll back only test data.
7. Record actual new-flow checks in the handoff; do not reuse first-version browser success as evidence for the revised UX.

Keep the procedural PHP, explicit PDO, ordinary loops, server-rendered HTML, and small XMLHttpRequest handlers. No framework, schema redesign, frontend build system, JSON endpoint, or extra database state is needed for this refinement.

## 12. Completion checks

- PHP syntax checks pass for every PHP file.
- Fresh project SQL imports successfully without modifying the existing `buffet` database.
- Missing/invalid/closed table URLs cannot order.
- Staff and manager login work; wrong credentials, deleted accounts, legacy inactive accounts, direct unauthorized pages, action URLs, and AJAX endpoints are rejected.
- Open table 1 for two guests; submit included foods and two 20-baht ice creams; order appears in the queue and customer status changes after serving.
- Place/cancel an 89-baht bingsu order; final bill remains 638 baht.
- Editing a menu price after ordering does not change the captured charge.
- Help and bill requests appear for staff; acknowledgment clears help.
- Checkout rejects unresolved preparing orders and prevents further orders after bill request/closure.
- Repeated submission does not create duplicate orders.
- Reopening table 1 creates a new visit; old orders and stale browser forms cannot affect it.
- Separate browsers/devices keep independent unsent carts; pages/tabs in one browser share the same visit-keyed draft. Navigation, refresh, help calls, and a rejected submission preserve quantities and notes.
- Rapid plus/minus taps and note edits are saved before navigating or submitting. Failed saves are visible and can be retried.
- A price changed after browsing requires a fresh review. Checkout rejects a total different from the amount the employee reviewed.
- Staff can clear an accidental bill request; customers resume ordering without starting another paid visit.
- Completed visit history remains readable without allowing an old page to write into the new group's visit.
- Manager form errors preserve non-password values and edit context; repeated staff operations retain useful context.
- Sold-out/deleted food cannot be ordered even through a manually changed POST.
- Manager CRUD works; staff cannot perform manager-only mutations; menu deletion preserves historical order data.
- Logout blocks subsequent protected requests; deleted accounts lose access on their next protected request.
- Daily totals count each finalized visit once.
- Customer and employee pages are usable at phone, tablet, and desktop widths; no missing image breaks the layout.
- Leave one small runnable check for the nontrivial order/billing rules and record the actual validation results in the handoff.
- README explains import/setup, example credentials, table URLs, demonstration steps, intentionally omitted JSON/membership/promotions, and the basic rubric mapping.

## 13. Source references

Context root: `C:\Users\teddymon\Desktop\project\context`.

- `documents/1-1 - Requirement.docx`: 11 customer requirements and 14 staff requirements; sold-out food stays visible.
- `documents/1-2 - Fedfe_Buffet_Requirement.html`: 11 customer requirements, 15 staff requirements, 7 business rules, 7 limitations, and rubric mapping; anonymous customers and secondary account CRUD are explicit.
- `documents/2 - Promotion.docx`: complete menu and three promotion designs, deliberately deferred here.
- `documents/3-1 Database Architecture.mmd`: eight-entity ERD.
- `documents/3-2 Seed Data Example.sql`: SQL definitions and fully checked synthetic data.
- `documents/3-3 รายการอาหาร และตำแหน่งไฟล์รูปภาพ.txt`: all 43 image filenames and categories.
- `documents/4 - Project Rubric.html`: eleven basic criteria with separate bonus tiers.
- `coding style/database/*`, `ajax/*`, `php/*`, `lecture-9/*`, `project/*`, `quiz3/*`, and `website/*`: procedural style and taught techniques.

The wireframe and application system architecture deliverables are not present in the supplied context. The existing ERD describes data relationships, not those missing page/layout and application-flow artifacts.

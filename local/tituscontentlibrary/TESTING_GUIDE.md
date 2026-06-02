# Titus Content Library — QA Testing Guide

Welcome! This guide walks you through testing the **Titus Content Library** plugin from end to end. You do not need any prior experience with Moodle. Just follow each step in order, compare what you see against the "Expected result" line, and write down anything that does not match.

---

## What you'll need

- A modern web browser (Chrome, Firefox, Edge or Safari — fully up to date).
- An internet connection.
- **No software installation is required.** Everything runs in the browser.
- The test site URL: **https://terrorless-keli-unhampered.ngrok-free.dev**
- Login credentials:
  - **Username:** `admin`
  - **Password:** `Admin1234!`
- A note-taking app (or pen and paper) to record any issues.
- A screenshot tool (built into your operating system is fine).

### A few terms before you start

So nothing surprises you, here is the Moodle vocabulary used in this guide:

- **Moodle** — the learning platform we are testing the plugin inside.
- **Course** — a unit of learning in Moodle. Think of it as a container that holds lessons, quizzes and activities.
- **Activity** — an item inside a course, for example a quiz, a forum or a SCORM package.
- **SCORM** — a standard format for packaged eLearning content (interactive slides, video, quizzes). When you "play" a SCORM activity in Moodle it opens like a mini-app inside the course.
- **Plugin** — an add-on that gives Moodle new features. The Titus Content Library is one of these plugins.
- **Adhoc task** — a one-off background job Moodle queues up and runs later. We will trigger these manually in this guide.
- **Simulator** — a separate plugin (`local_titusclsim`) that pretends to be the real Titus Learning service while we are testing. It returns fake catalogue data so the main plugin has something to talk to.

📌 **Tip:** If a page looks blank or broken, refresh once with `Ctrl+F5` (or `Cmd+Shift+R` on Mac) before reporting it. This forces a clean reload.

---

## Section 1 — First login

### Step 1.1 — Open the test site

Open your browser and go to: **https://terrorless-keli-unhampered.ngrok-free.dev**

✅ **Expected result:** A page loads showing the Moodle login screen with two fields ("Username" and "Password") and a blue "Log in" button.

⚠️ If the page shows an ngrok warning page ("You are about to visit…"), click the **"Visit Site"** button to continue. This is normal for our test environment.

### Step 1.2 — Log in as the admin

Type `admin` into the **Username** field and `Admin1234!` into the **Password** field. Click **Log in**.

✅ **Expected result:** You land on the Moodle **Dashboard**. You should see your name ("Admin User") in the top-right corner and a left-hand side menu with items like "Home", "Dashboard", "My courses" and "Site administration".

---

## Section 2 — Verify the admin settings

We need to make sure the plugin is configured correctly before we use it.

### Step 2.1 — Open the plugin settings page

In the left-hand menu click **Site administration**. (If it is not visible, click the menu icon — three horizontal lines — in the top-left corner first.)

On the Site administration page, click the **Plugins** tab at the top. Scroll down to the **Local plugins** section and click **Titus Content Library**.

The direct URL is: **https://terrorless-keli-unhampered.ngrok-free.dev/admin/settings.php?section=local_tituscontentlibrary**

✅ **Expected result:** A settings page titled "Titus Content Library" appears with four fields:

1. **Titus API base URL** — should be filled in (it points to the simulator).
2. **Licence key** — shows a masked / hidden value (dots or asterisks). This is encrypted, so it should not be readable as plain text.
3. **Default course category** — a dropdown with at least the "Miscellaneous" option.
4. **Catalogue cache lifetime** — a number (in seconds).

You should also see a button labelled **Test connection** somewhere on the page.

### Step 2.2 — Press the "Test connection" button

Click **Test connection**.

✅ **Expected result:** Within a couple of seconds a green message appears near the button reading **"connexion:ok"** (or similar success wording). No page reload happens — the check is done in the background.

⚠️ If you see a red error message, copy the exact text and note it in your report. **Do not change any settings** — just record what you see.

### Step 2.3 — Confirm settings are persisted

Refresh the page (`F5`).

✅ **Expected result:** All four fields still show the same values. Nothing was lost.

---

## Section 3 — Browse the Titus Marketplace

The Marketplace is where users browse Titus content and add it to Moodle.

### Step 3.1 — Open the Marketplace

Go to: **https://terrorless-keli-unhampered.ngrok-free.dev/local/tituscontentlibrary/index.php**

(You can also reach it through Site administration → Plugins → Local plugins → Titus Content Library → "Open marketplace", if a link is present.)

✅ **Expected result:**

- A page titled **"Titus Marketplace"** (or similar) loads.
- You see a **grid of content tiles** — exactly **12 tiles** should be visible.
- At the top of the page there is a **search bar**.
- Below the search bar (or alongside it) there are **category pill buttons** — small rounded buttons each showing a category name like *Leadership*, *Technology*, *Finance*, etc.
- There is a **sort dropdown** (showing something like "Sort by…" or "A-Z" by default).
- Each tile shows:
  - A **thumbnail image** at the top.
  - A **title** (the name of the SCORM course).
  - A **short description**.
  - A small **duration badge** (e.g. "30 min", "45 min").
  - One or more **tags** (small coloured labels).
  - A blue/primary **"Add to Moodle"** button.
  - A secondary **"View Details"** button.

### Step 3.2 — Confirm the content tiles by name

Look through the 12 tiles and confirm you can see (at minimum) a tile titled **"Leadership Foundations"**. The other tiles are themed around Leadership, Technology, Finance, Compliance, Communication and similar topics.

✅ **Expected result:** The "Leadership Foundations" tile is visible. All 12 tiles render fully (no broken images, no missing titles).

📌 Note down any tile that looks broken (missing image, missing title, missing button).

---

## Section 4 — Search for content

### Step 4.1 — Type a search term

Click inside the **search bar** at the top of the Marketplace page. Type the word **`Leadership`** slowly.

✅ **Expected result:**

- As you type, the grid **filters automatically** after a tiny pause (about a third of a second after you stop typing — this is the "debounce").
- Only tiles whose **title, description or tags contain "Leadership"** remain visible.
- The "Leadership Foundations" tile is one of the remaining tiles.
- Tiles that do not match disappear smoothly.

### Step 4.2 — Clear the search

Erase the text in the search bar (select all and delete, or backspace until empty).

✅ **Expected result:** All 12 tiles return to the grid.

---

## Section 5 — Filter by category

### Step 5.1 — Click a category pill

Find the **category pill buttons** under (or near) the search bar. Click the one labelled **"Leadership"**.

✅ **Expected result:**

- The clicked pill changes appearance (it looks "selected" — usually a darker or filled background).
- The grid narrows down to **only tiles in the Leadership category**.
- Tiles not in that category are hidden.

### Step 5.2 — Click another category

Click a different pill, for example **"Technology"**.

✅ **Expected result:** The Leadership pill becomes deselected, the Technology pill becomes selected, and the grid now shows only Technology tiles.

### Step 5.3 — Clear the category filter

Click the same Technology pill again (or look for an "All" pill if it exists) to deselect it.

✅ **Expected result:** All 12 tiles return.

---

## Section 6 — Use the sort dropdown

### Step 6.1 — Open the sort dropdown

Find the **sort dropdown** (usually next to the search bar). Click it to open it.

✅ **Expected result:** A list of sort options appears. You should see at least:

- A-Z
- Z-A
- Newest
- Duration
- Category
- Featured
- New

### Step 6.2 — Try each sort option

Click each option one at a time. After each click:

✅ **Expected result:**

- **A-Z** — tiles re-order alphabetically by title, A first.
- **Z-A** — tiles re-order alphabetically, Z first.
- **Newest** — most recently published tiles first.
- **Duration** — tiles ordered by duration (short to long, or grouped by length).
- **Category** — tiles grouped by category.
- **Featured** — featured tiles appear first.
- **New** — tiles marked "new" appear first.

📌 You do not need to know which is "correct" — just confirm the **order visibly changes** when you pick a different option. Note any option that has no effect or causes an error.

---

## Section 7 — View the Details modal

### Step 7.1 — Open a tile's details

Find the **"Leadership Foundations"** tile and click its **"View Details"** button.

✅ **Expected result:** A **modal window** (a pop-up that overlays the page and dims the background) opens. It contains:

- The **title** of the course ("Leadership Foundations").
- A **longer description** (more text than the short description on the tile).
- The **duration** of the course.
- The list of **tags** assigned to it.
- A close button (an "X" in the corner, or a "Close" button at the bottom).

### Step 7.2 — Close the modal

Click the **X** or **Close** button (or press the `Esc` key).

✅ **Expected result:** The modal closes smoothly and you are back on the Marketplace grid with no leftover overlay.

---

## Section 8 — Add a course to Moodle

This is the main feature: pulling content from the Titus catalogue into Moodle as a real course.

### Step 8.1 — Click "Add to Moodle"

Find the **"Leadership Foundations"** tile again. Click its **"Add to Moodle"** button.

✅ **Expected result — watch the button closely:**

- Immediately the button text changes to **"Queuing…"** (with a spinner or activity indicator).
- After a moment it changes to **"Queued"** (the request has been accepted and a background job has been scheduled).
- The button is now disabled (you cannot click it again).

📌 **Important:** The tile is now waiting for a background job to actually create the course. That job does not run on its own during testing — we will run it manually in the next section.

⚠️ If the button stays stuck on "Queuing…" for more than 10 seconds, or shows an error, take a screenshot and note it.

---

## Section 9 — Run the background task manually

In production, Moodle runs background jobs automatically every minute. In testing we trigger them by hand so we do not have to wait.

### Step 9.1 — Go to the adhoc task queue

Open a new browser tab and go to: **https://terrorless-keli-unhampered.ngrok-free.dev/admin/tool/task/adhoctasks.php**

(Or in the Moodle menu: **Site administration → Server → Tasks → Adhoc task queue**.)

✅ **Expected result:** A page titled "Adhoc tasks" loads showing a table of pending background jobs. You should see at least one row with the task class name **`add_content_task`** (the full name may be something like `local_tituscontentlibrary\task\add_content_task`).

### Step 9.2 — Run the task now

In the row for `add_content_task`, find the **"Run this task now"** link or button (usually in an "Actions" column at the right). Click it.

✅ **Expected result:**

- A confirmation page may appear asking "Are you sure?" — click **Continue** or **Yes**.
- A page of log output is shown while the task runs. After a few seconds you should see a message such as **"Adhoc task complete"** or **"Task succeeded"**.
- There are no red "Exception" or "Error" lines in the output.

### Step 9.3 — Return to the Marketplace

Go back to the Marketplace tab (or open it again): **https://terrorless-keli-unhampered.ngrok-free.dev/local/tituscontentlibrary/index.php**

Refresh the page with `F5`.

✅ **Expected result:** The **"Leadership Foundations"** tile now shows:

- The status **"Added"** (in place of the old "Add to Moodle" button).
- A new link or button labelled **"Open Course"**.

⚠️ If the tile still says "Queued", the background task may not have finished. Wait 5 seconds, refresh again, and re-check the adhoc task queue to confirm the task is gone (which means it completed).

---

## Section 10 — Open the new course

### Step 10.1 — Click "Open Course"

On the "Leadership Foundations" tile, click **"Open Course"**.

✅ **Expected result:**

- You are taken to a Moodle course page titled **"Leadership Foundations"**.
- Inside the course, in the main content area, there is a **SCORM activity** (it usually has a small icon that looks like a stack of papers or a blue cube). It will be named something like "Leadership Foundations" or the SCORM package name.

### Step 10.2 — (Optional) Open the SCORM activity

Click the SCORM activity name.

✅ **Expected result:** A page loads showing the SCORM package details with an **"Enter"** or **"Preview"** button. You do not need to play through the SCORM — just confirm the activity exists and the entry page loads without errors.

📌 You can click the browser's **Back** button to return.

---

## Section 11 — Check the Manage page

### Step 11.1 — Open Manage

Go to: **https://terrorless-keli-unhampered.ngrok-free.dev/local/tituscontentlibrary/manage.php**

✅ **Expected result:**

- A page titled **"Manage"** (or "Manage Content") loads.
- A table lists all content items that have been added to Moodle from the Titus Library.
- One row should be for **"Leadership Foundations"**.
- Each row has at minimum:
  - The content title.
  - Some metadata (date added, course link, etc.).
  - Two action buttons or links: **"Re-sync"** and **"Remove"**.

---

## Section 12 — Re-sync content

Re-sync re-downloads the latest version of the SCORM package from Titus and refreshes the Moodle course.

### Step 12.1 — Click Re-sync

On the **"Leadership Foundations"** row, click **"Re-sync"**.

✅ **Expected result:**

- A confirmation dialog or page asks **"Are you sure you want to re-sync this content?"** (or similar wording).

### Step 12.2 — Confirm

Click **Yes** / **Confirm** / **Continue**.

✅ **Expected result:**

- A short on-screen message confirms the resync has been **queued** (for example "Re-sync queued" or a green notification banner).
- You return to the Manage page.

### Step 12.3 — Run the resync background task

Open the adhoc task queue again: **https://terrorless-keli-unhampered.ngrok-free.dev/admin/tool/task/adhoctasks.php**

✅ **Expected result:** A new row with the task class **`resync_content_task`** is in the table.

### Step 12.4 — Run it

Click **"Run this task now"** for `resync_content_task`. Confirm if prompted.

✅ **Expected result:** The output page reports the task **completed successfully** with no errors. The task disappears from the queue after completion.

### Step 12.5 — Verify the course is still there

Go back to the Manage page (**https://terrorless-keli-unhampered.ngrok-free.dev/local/tituscontentlibrary/manage.php**).

✅ **Expected result:** "Leadership Foundations" is still in the list and looks unchanged. The associated Moodle course (Step 10) still exists and still opens.

---

## Section 13 — Remove the content row

Removing a row deletes the tracking record only — the Moodle course itself stays in place. We will verify both.

### Step 13.1 — Click Remove

On the **"Leadership Foundations"** row in the Manage page, click **"Remove"**.

✅ **Expected result:**

- A confirmation dialog asks **"Are you sure you want to remove this content?"** (or similar).
- The message should make it clear that this only removes the tracking row, not the Moodle course itself.

### Step 13.2 — Confirm

Click **Yes** / **Confirm**.

✅ **Expected result:**

- A success message appears (for example "Content removed").
- The row for "Leadership Foundations" **disappears** from the table.

### Step 13.3 — Verify the Moodle course still exists

Go to: **https://terrorless-keli-unhampered.ngrok-free.dev/my/courses.php**

(Or click **My courses** in the left-hand menu.)

✅ **Expected result:** The **"Leadership Foundations"** course is still listed. Click it to confirm it still opens and still contains the SCORM activity from Step 10.

📌 This is important: removing from the Titus Library should never delete the Moodle course.

---

## Section 14 — Verify simulator licences page

The simulator stands in for the real Titus service during testing. The licence key drives whether catalogue access is allowed.

### Step 14.1 — Open the licences page

Go to: **https://terrorless-keli-unhampered.ngrok-free.dev/local/titusclsim/admin/licences.php**

✅ **Expected result:**

- A page titled **"Licences"** (or similar) loads.
- A table lists licence keys.
- The key **`TITUS-FULL-KEY-001`** appears in the list.
- Its **tier** column shows **`full`**.
- Its **status** column shows **"Enabled"** (or a green tick, or similar positive indicator).

### Step 14.2 — Check the catalogue admin page (placeholder)

Go to: **https://terrorless-keli-unhampered.ngrok-free.dev/local/titusclsim/admin/catalogue.php**

✅ **Expected result:** A page loads without errors. It is currently a **placeholder** (it may say "Coming soon", "Under construction", or just display the page title with no real management controls). That is correct for now — just confirm the page does not crash.

---

## You're done — wrap-up checklist

Quickly tick off each section you completed:

- [ ] Section 1 — Logged in successfully
- [ ] Section 2 — Settings page loads, "Test connection" returns success
- [ ] Section 3 — 12 tiles visible on Marketplace
- [ ] Section 4 — Search filters tiles as you type
- [ ] Section 5 — Category pills filter the grid
- [ ] Section 6 — All sort options change the order
- [ ] Section 7 — Details modal opens and closes
- [ ] Section 8 — "Add to Moodle" transitions through Queuing → Queued
- [ ] Section 9 — Background task runs and tile becomes "Added"
- [ ] Section 10 — Course opens with SCORM activity present
- [ ] Section 11 — Manage page lists the added content
- [ ] Section 12 — Re-sync queues and runs successfully
- [ ] Section 13 — Remove deletes the row but keeps the course
- [ ] Section 14 — Simulator licence `TITUS-FULL-KEY-001` is shown as enabled

---

## What to report

When you finish, send a single report containing:

1. **A summary line** — e.g. "All 14 sections passed" or "12 of 14 passed, 2 failed".
2. **For each failure**, include:
   - **The section and step number** (e.g. "Section 8, Step 8.1").
   - **The full URL** of the page where the failure happened (copy it from the browser address bar).
   - **The exact error message** if one is shown — copy and paste, do not paraphrase.
   - **A screenshot** of the failure (include the full browser window so the URL bar is visible).
   - **What you expected to see** vs **what you actually saw**.
   - **Steps to reproduce** if it might not be obvious from the guide.
3. **Browser details** — name and version (e.g. "Chrome 125 on Windows 11").
4. **Approximate time** the failure happened (so the developer can match it against server logs).

📌 If something is just visually odd but still works (a button slightly misaligned, a colour that looks off), note it under a separate "Cosmetic issues" heading rather than mixing it with functional failures.

⚠️ **If the whole site is unreachable**, first try refreshing once, then try opening any other page on the site. If nothing loads at all, stop testing and report immediately — the dev environment may be down.

Thank you for testing!

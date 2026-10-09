# APRISM UI/UX, Responsiveness & Interaction Baseline


**Project:** APRISM: An AprilTag-Based Attendance, Assessment and Intervention Support System for Academic Personnel at STI College Dasmariñas  
**Purpose:** Standing reference for all future APRISM UI/UX, visual, interaction, responsive, accessibility, and usability work.  
**Audience:** Codex / implementation agent, project owner, reviewers.  
**Status:** Consolidated baseline. Read this before changing any user-facing APRISM page.


---


## 0. Non-negotiable framing


APRISM is an **internal academic personnel system**.


It is **not**:
- a marketing website;
- a social-media app;
- a portfolio;
- a consumer SaaS landing page;
- a generic admin-dashboard template;
- a place to add visual effects simply because they look modern.


A good APRISM screen makes the correct information and action obvious, preserves real academic/system logic, and adapts naturally across devices.


The final design sequence is:


> **information priority → grouping and spacing → interaction clarity → responsive recomposition → visual polish**


Never reverse that order.


---


## 1. Decision priority / source of truth


When deciding what a page should look like or how it should behave, use this order:


1. **Current APRISM runtime and real data**
2. **APRISM frozen domain rules, permissions, project/MD guidance**
3. **Technical Administrator and strongest existing APRISM screens**
4. **Previously approved APRISM UI/UX decisions**
5. **This baseline**
6. **External design systems, websites, TikTok/UI references, UX laws**


External references are evidence, not authority.


If a reference is attractive but conflicts with APRISM workflow, data, permissions, role scope, or established design, **reject the reference**.


---


## 2. APRISM visual character


APRISM should feel:
- calm;
- deliberate;
- compact without being cramped;
- professional and academic;
- information-first;
- easy to scan;
- predictable;
- trustworthy with data;
- responsive rather than merely scaled down.


APRISM should not feel:
- decorative for decoration's sake;
- over-carded;
- overly rounded or “cute”;
- like social media;
- like a portfolio;
- like a finance dashboard full of fake richness;
- empty because panels were stretched to fill the viewport;
- compressed into tiny text merely to avoid scrolling;
- visually noisy with unnecessary gradients, shadows, badges, charts, icons, or animation.


---


## 3. Existing APRISM identity stays


Keep the established visual direction unless the project owner explicitly changes it:


- navy sidebar;
- yellow active navigation state;
- white/light-gray workspace;
- blue primary action color;
- restrained borders and shadows;
- local Inter typography;
- local Lucide icons;
- compact title rows;
- compact context metadata;
- top-right stacked toasts;
- Technical Admin as the strongest shared visual baseline.


Do not replace APRISM with a trendy external visual language.


---


## 4. Standardize the design system, not every page


Shared consistency should come from:
- typography;
- spacing tokens;
- control heights;
- border color;
- radius;
- shadow strength;
- table behavior;
- button hierarchy;
- badges/statuses;
- focus states;
- empty states;
- responsive breakpoints and patterns.


Different tasks may still require different page compositions.


A dashboard should not be forced to look like an import workflow.
A detail page should not be forced to look like a registry.
A scanner should not be forced into a table-first composition.


---


# PART I — INFORMATION ARCHITECTURE & HIERARCHY


## 5. One clear purpose per section


Every section should answer one clear question or support one clear task.


Good:
- Attendance concerns
- Backup history
- Submitted referrals
- Academic placement
- Schedule records


Avoid containers that exist only to make the page look fuller.


---


## 6. Group similar things together


This was strongly reinforced by the reference material.


Use proximity and similarity intentionally:
- related filters stay together;
- related metadata stays together;
- related statuses stay together;
- related actions stay together;
- a validation message stays near the affected field;
- class context stays with the class;
- student identity fields stay together.


Use spacing and alignment before adding another card.


---


## 7. Chunk complex information


Do not present complex information as one undifferentiated block.


Chunk by meaning:
- identity;
- academic context;
- schedule;
- readiness;
- status;
- actions;
- import stage;
- validation outcome.


This applies especially to:
- Class List import;
- Teacher class rows;
- student placement;
- referral/case details;
- grade import;
- attendance/scanner states.


---


## 8. At-a-glance rule


A user should quickly know:
- Where am I?
- What context am I in?
- What needs attention?
- What can I do?
- What state is this in?


Do not make users decode visual decoration to understand status.


---


## 9. Progressive disclosure


Show what the user needs now.
Reveal secondary complexity when needed.


Good uses:
- advanced filters;
- secondary row actions;
- extra details;
- import resolution steps;
- mobile overflow menus.


Bad uses:
- hiding the primary action;
- hiding critical status;
- hiding required academic context;
- putting ordinary actions several clicks deep.


---


# PART II — SPACING, PROPORTION & WHITESPACE


## 10. 8px-oriented spacing rhythm


The references included the 8px rule. Use it as a consistency tool, not a religion.


Practical scale:
- 4px — tiny internal relationship;
- 8px — tightly related elements;
- 12px — compact component spacing;
- 16px — standard internal spacing;
- 20–24px — component/section separation;
- 32px — major page separation on desktop.


Do not rewrite established APRISM geometry merely to make every value divisible by 8.


---


## 11. Whitespace must communicate meaning


Whitespace is useful when it:
- separates concepts;
- improves scanning;
- reduces visual noise;
- gives important content breathing room.


Whitespace is bad when it:
- creates dead zones;
- exists only to force equal-height panels;
- makes short content feel lost in giant containers;
- pushes useful actions below the fold for no reason.


---


## 12. Stop “maximizing white space” as a universal rule


Default rule:


> **Fluid width + content-driven height + viewport-aware composition.**


Use remaining viewport height only when it genuinely benefits a bounded workspace, preview, scanner, or data region.


Do not stretch:
- table rows;
- cards;
- list items;
- metric blocks;
- form controls;
- backup entries


just to fill a screen.


Registry/list pages generally use natural content height.


---


## 13. Adaptive row alignment


If one desktop row becomes taller because one column has more content, shorter sibling blocks may vertically center when that improves balance.


Example: My Classes
- schedule can determine row height;
- Operational Class, Readiness, and Actions may vertically center;
- their internal horizontal alignment remains natural;
- mobile returns to normal stacked flow.


Header alignment and row-content alignment are separate decisions.


---


# PART III — TYPOGRAPHY & CONTENT


## 14. Hierarchy before size inflation


Use:
- restrained page title;
- clear section title;
- compact supporting context;
- readable body text.


Avoid:
- huge editorial headings;
- unnecessary all-caps;
- generic subtitles that repeat the title;
- oversized “hero” treatments inside an internal system.


---


## 15. Human, direct wording


Prefer:
- Import classes
- Create backup
- Assign adviser
- View student
- Placement unresolved
- Replace tag


Avoid vague labels when the result can be stated:
- Proceed
- Manage
- Continue
- Submit data


“Continue” is acceptable inside a true multi-step flow when the next step is obvious.


---


## 16. Metadata stays subordinate


Academic year, semester, class, program, section, counts, timestamps, etc. should be visible but visually secondary.


Do not turn metadata into a large banner.


---


# PART IV — ACTIONS & CTA HIERARCHY


## 17. Primary CTA must stand out


The references explicitly reinforced this.


Normally one action should visually dominate the current task.


Examples:
- Import Classes
- Create Backup
- Confirm Import
- Assign Tags
- Save Changes
- Submit Referral


Secondary actions must not compete equally.


---


## 18. Outcome-driven labels


Buttons should describe the result where possible:
- Import students
- Download backup
- View details
- Replace tag
- Clear filters


Avoid vague action copy.


---


## 19. Too many actions


If a row/page exposes many low-frequency actions:
- keep the most important action visible;
- group closely related actions;
- move secondary actions to a compact menu on smaller screens when appropriate;
- never hide the core task just to save space.


---


## 20. Destructive actions


Destructive actions require:
- distinct styling;
- clear wording;
- confirmation when consequences matter.


Do not use destructive styling for ordinary Cancel/Close.


---


# PART V — CARDS & METRICS


## 21. One important value per KPI card


A metric card should normally have:
- one dominant value;
- one clear label;
- optional short supporting context.


Do not make every detail equally prominent.


---


## 22. No fake richness


Never add:
- invented percentages;
- fake trends;
- decorative arrows;
- unsupported risk scores;
- made-up counts;
- meaningless mini charts.


APRISM's real data is authoritative.


---


## 23. Card-based choices only when the choice is rich


The reference comparing radio buttons vs cards is useful in context.


Use choice cards when users genuinely need to compare meaningful options with multiple attributes.


Do **not** replace:
- simple role selects;
- status filters;
- semester selectors;
- basic yes/no options


with giant cards.


---


## 24. Avoid nested-card overload


Use hierarchy, whitespace, alignment, and dividers before adding another card.


Not every group needs a border and radius.


---


# PART VI — CHARTS, GRAPHS & VISUALIZATION


## 25. Graphs must answer a real question


The references included visually strong dashboards and charts. Borrow the hierarchy, not the fake richness.


A chart is justified when it answers something more clearly than a number/table.


Good APRISM questions:
- How has attendance changed across sessions?
- Which classes have the most attendance concerns?
- How many referrals are open vs resolved?
- How complete are grade records by academic period?
- Is there a meaningful attendance trend over time?


Bad reason:
- “The dashboard looks empty.”


---


## 26. Chart type rules


Use:
- **Line** — ordered time trend;
- **Bar** — compare categories/counts;
- **Stacked bar** — meaningful additive composition;
- **Pie/donut** — a small number of parts of one real whole;
- **Progress/indicator** — real percentage/threshold with operational meaning.


Avoid:
- donut charts just because they look modern;
- pie charts with many slices;
- noisy line charts with too few meaningful points;
- raw-grade visualizations that do not aid decisions.


---


## 27. Chart clarity


Every chart needs:
- concise title;
- understandable axes/units;
- readable labels;
- restrained legend;
- sufficient contrast;
- truthful scale;
- no label clutter.


If a table/number is clearer, use the table/number.


---


## 28. APRISM chart caution


Teacher Dashboard already showed why this matters: raw grades can become visually noisy.


Prefer:
- missing-entry counts;
- completion summaries;
- meaningful distributions;
- attendance trends with sufficient sessions;
- actionable referral summaries.


No decorative analytics.


---


# PART VII — TABLES & DATA-DENSE UI


## 29. APRISM's own strong tables remain the baseline


The reference material did not provide a better administrative-table model.


Continue using:
- Technical Admin;
- Academic Head consolidated registries;
- established APRISM table/pagination rules


as the main table references.


---


## 30. Desktop table rules


- keep only useful columns;
- combine related information when it improves scanning;
- avoid multiple columns full of em dashes;
- maintain compact readable rows;
- identity information appears early;
- status is easy to locate;
- actions are bounded and clear;
- page-level horizontal scrolling is not acceptable.


---


## 31. Simplify before shrinking


When width decreases:


1. remove/reorganize redundant columns;
2. combine related fields;
3. allow sensible wrapping;
4. stack secondary information within a cell;
5. transform into cards when the table is no longer readable.


Never solve responsiveness by making text tiny.


---


## 32. Mobile table transformation


When a wide table cannot remain readable, transform records into labeled cards.


Preserve:
- identity;
- key status;
- essential metadata;
- primary action;
- needed secondary actions.


Avoid left/right page dragging on phones unless the data is inherently matrix-like.


---


## 33. Pagination


Pagination must reflect real records.


- hide pagination when only one page exists;
- do not invent pages;
- keep controls touch-friendly;
- avoid redundant range text if it adds no information;
- dashboard previews remain previews, not full registries.


---


## 34. Bounded data regions


A bounded table region may use remaining height when helpful.


But:
- rows remain natural height;
- pagination stays reachable;
- short laptop heights must not create pointless scroll;
- visible row capacity may adapt to viewport height.


---


# PART VIII — SEARCH & FILTERS


## 35. Filters are one logical group


Search, role, status, academic context, dates, etc. should read as one coherent filtering area.


---


## 36. Responsive filter reflow


Desktop:
- balanced horizontal layout is fine.


Medium:
- wrap/reduce columns.


Phone:
- stack where needed.


Never squeeze many desktop controls into unusably narrow widths just to preserve one row.


---


## 37. Labels vs placeholders


Placeholders are hints/examples, not a replacement for labels where persistent context is needed.


---


## 38. Active filter state


Users should be able to understand:
- results are filtered;
- which filters are active;
- how to clear/reset when appropriate.


---


# PART IX — FORMS


## 39. Single-column forms on narrow screens


This was explicitly reinforced by the references.


Phone and narrow tablet forms should normally be single-column.


Desktop may use multiple columns only when fields naturally belong together.


---


## 40. Form order follows the user's thought process


Do not arrange fields merely because the database schema is ordered that way.


---


## 41. Validation appears near the problem


The “Instant Errors” reference is directly useful.


When practical:
- show validation beside/below the affected field;
- preserve entered data;
- explain how to fix the error;
- retain server-side validation as authority.


A generic top alert can supplement inline errors, not replace them in complex flows.


---


## 42. Prevent errors where practical


Use:
- clear constraints;
- disabled states when justified;
- confirmation for destructive/high-impact actions;
- sensible defaults;
- validation before irreversible confirmation.


Do not make users guess why a button cannot work.


---


# PART X — IMPORT / UPLOAD WORKFLOWS


## 43. Show meaningful process state


The reference showing selected-file state and progress is useful **only where APRISM really supports those states**.


For imports show, when available:
- accepted file types;
- selected filename;
- replace/remove file;
- extraction state;
- mapping state;
- review state;
- validation state;
- confirm state;
- completion state.


---


## 44. Progress must be real


Do not fake percentage progress for an operation where APRISM has no meaningful progress data.


Use discrete states when that is what the system actually knows.


---


## 45. Make import errors actionable


Identify:
- affected row;
- affected field;
- actual issue;
- what the user can fix.


Preserve valid work where workflow allows.


---


## 46. Multi-step import pattern


Use explicit steps when complexity warrants them:


> Upload → Extract/Map → Review → Resolve/Validate → Confirm


Do not add unnecessary steps simply to look sophisticated.


---


# PART XI — EMPTY, LOADING, SUCCESS & ERROR STATES


## 47. Clear empty states


The “Clear Empty States” reference is useful.


A good empty state explains:
1. what the state means;
2. what the user can do next, when an action exists.


Examples:
- No attendance concerns found.
- No students are currently assigned.
- No backups have been created yet.


---


## 48. Do not over-illustrate emptiness


APRISM does not need a decorative illustration for every empty state.


A simple icon + short title + short explanation + optional CTA is enough.


---


## 49. Loading states only where waiting exists


Do not add skeleton screens everywhere simply because modern references show them.


Use loading feedback when there is a real wait.


---


## 50. Error recovery


Error states should answer:
- What failed?
- Was my work preserved?
- What should I do next?


Avoid exposing raw technical errors when a human explanation is possible.


---


## 51. Success feedback


Use established top-right toasts for routine success.


Do not interrupt routine work with unnecessary success modals.


---


# PART XII — NAVIGATION


## 52. Strong location awareness


Users should know where they are through:
- active sidebar item;
- page title;
- compact context when necessary.


The “strong logo location” reference reinforces predictable anchoring, but APRISM already has its sidebar brand anchor. Do not relocate the logo just to copy the example.


---


## 53. Role navigation remains authoritative


Do not introduce social/consumer navigation patterns.


Navigation follows APRISM roles and Teacher responsibility extensions.


---


## 54. Small-screen navigation plan


The reference “Have a Plan for Small Screens” is important.


On small screens:
- navigation may collapse;
- secondary destinations can sit in a menu;
- active location remains obvious;
- core destinations stay easy to reach;
- hidden options should not require deep navigation.


---


## 55. Core tasks stay visible


Do not hide the main workflow action behind an overflow menu merely because space is tight.


---


# PART XIII — RESPONSIVENESS


## 56. Responsive means recomposition, not shrinking


APRISM must adapt layout, priority, grouping, and interaction to available space.


Do not build desktop and then merely scale it down.


---


## 57. Viewport checkpoints


Test at minimum:


| Category | Viewport |
|---|---:|
| Large desktop | 1920×1080 |
| Large laptop | 1536×864 |
| Main desktop reference | 1440×900 |
| Common tight laptop | 1366×768 |
| Short laptop stress | 1280×720 |
| Small laptop | 1280×800 |
| Narrow desktop/tablet landscape | 1024×768 |
| Tablet portrait | 768×1024 |
| Phone | 390×844 |
| Larger phone | 430×932 |


These are **QA checkpoints, not the only supported screen sizes**.


Behavior must be fluid between them.


---


## 58. Height matters as much as width


1366×768 and 1280×720 are especially important.


A design can look perfect at 1440×900 and fail on another laptop because of shorter height.


---


## 59. Short-laptop behavior


Before shrinking text:
- remove unnecessary vertical spacing;
- allow legitimate vertical scroll;
- avoid bad fixed/min heights;
- keep buttons/fields readable;
- prevent panels from adding a few pointless pixels of scroll.


---


## 60. Mobile recomposition


On mobile:
- sidebar/navigation collapses appropriately;
- KPI layouts reflow;
- forms become single-column;
- filter rows stack/wrap;
- tables become cards when needed;
- secondary actions can move into menus;
- primary actions remain visible;
- dialogs fit the viewport;
- content stays readable.


---


## 61. Equal heights are contextual, not universal


Equal-height panels are useful when they belong to the same desktop row and visual comparison benefits.


When stacked:
- revert to natural height;
- do not preserve desktop blank space.


---


# PART XIV — SCROLLING


## 62. Horizontal page scrolling


Normal APRISM pages should **not** require horizontal page scrolling.


Rare exceptions must be justified by genuinely matrix-like data.


---


## 63. Vertical scrolling


Vertical scrolling is normal when the content needs it.


Do not crush the UI merely to keep everything above the fold.


---


## 64. Avoid unnecessary scroll


Fix vertical scroll caused by:
- giant margins;
- redundant subtitles;
- bad fixed heights;
- panels stretched beyond useful content;
- oversized cards;
- excessive spacing.


---


## 65. Above-the-fold priority


Try to keep:
- page identity;
- important context;
- primary action;
- first important data/state


visible early.


Do not force the entire page to fit one screen.


---


# PART XV — TOUCH, KEYBOARD & ACCESSIBILITY


## 66. Touch targets


On touch layouts, interactive targets should generally be around **44px or larger**.


This includes:
- icon buttons;
- pagination;
- row actions;
- nav items;
- dropdown triggers;
- scanner actions.


---


## 67. Do not rely on hover


Critical meaning and actions must work without hover.


---


## 68. Keyboard usability


Interactive controls must:
- be focusable;
- show visible focus;
- activate correctly by keyboard where expected.


---


## 69. Accessibility essentials


Maintain:
- sufficient contrast;
- readable text;
- semantic headings;
- labels for controls;
- accessible names for icon-only buttons;
- focus visibility;
- status not communicated by color alone;
- errors not communicated by color alone.


---


# PART XVI — STATUS, BADGES & PILLS


## 70. Semantic status direction


Typical APRISM use:
- green — complete / ready / valid / success;
- amber — pending / incomplete / needs action;
- red — error / destructive / serious issue;
- blue — informational / neutral active;
- gray — neutral / not started / secondary.


Use text + color, not color alone.


---


## 71. Do not pill everything


Badges/pills should communicate states, categories, or compact labels.


Ordinary body text does not need a pill.


---


# PART XVII — UX LAWS / BEHAVIORAL PRINCIPLES


Use UX laws as heuristics, not rigid commandments.


## 72. Proximity / Gestalt grouping


Close elements feel related.


APRISM use:
- filters;
- metadata;
- field + error;
- status/action groups.


---


## 73. Similarity


Elements that look alike are perceived as having similar meaning.


APRISM use:
- consistent buttons;
- status pills;
- table actions;
- metric cards.


---


## 74. Fitts's Law


Important/frequent targets should be easy to hit.


APRISM use:
- comfortable mobile controls;
- visible main CTA;
- scanner controls;
- larger tap regions.


---


## 75. Hick's Law


More visible choices increase decision time.


APRISM use:
- do not expose many equal-priority actions;
- use progressive disclosure for secondary options.


---


## 76. Jakob's Law


Users expect familiar interface behavior.


APRISM use:
- conventional search;
- forms;
- menus;
- pagination;
- tables;
- validation.


Do not invent unusual interactions without a clear reason.


---


## 77. Cognitive chunking / Miller principle


Grouped information is easier to understand than undifferentiated density.


APRISM use:
- import steps;
- class context;
- placement information;
- limited KPI sets.


---


## 78. Tesler's Law / conservation of complexity


Some domain complexity cannot be removed.


APRISM use:
- do not hide required academic context to look simpler;
- put unavoidable complexity into clear workflow stages.


---


## 79. Aesthetic-usability effect


A coherent interface can feel easier to use, but attractive visuals do not fix bad workflow.


Polish comes after correctness.


---


## 80. Doherty threshold / perceived responsiveness


Users need fast feedback.


APRISM use:
- pressed/loading states where appropriate;
- toasts;
- immediate validation response;
- no dead-feeling controls.


---


## 81. Error prevention over error messages


Prevent mistakes where practical, but always support recovery.


APRISM use:
- import review;
- AprilTag safeguards;
- confirmations;
- inline validation;
- disabling impossible actions when justified.


---


# PART XVIII — MICRO-INTERACTIONS


## 82. Use subtle feedback


Good:
- hover/focus;
- pressed state;
- real loading state;
- toast;
- smooth expand/collapse.


Avoid:
- bounce effects;
- decorative loops;
- flashy page transitions;
- animation that slows academic work.


---


# PART XIX — MODALS, DRAWERS & MENUS


## 83. Modals


Use for a bounded temporary task that benefits from preserving page context.


Do not force long multi-step workflows into cramped modals when a page is clearer.


---


## 84. Mobile modal behavior


- fit viewport;
- body scrolls if needed;
- no horizontal overflow;
- actions remain reachable;
- avoid huge fixed widths/heights.


---


# PART XX — PAGE-TYPE RULES


## 85. Dashboards


Use:
- compact title/context;
- limited meaningful KPI cards;
- one/two useful summary regions;
- real charts only when informative;
- previews linking to full data where useful.


Avoid:
- too many metrics;
- decorative analytics;
- giant blank panels;
- universal equal-height forcing.


---


## 86. Registry / management pages


Examples:
- Users;
- Students;
- Schedules;
- Audit Logs;
- Backups.


Use:
- natural content height;
- search/filter group;
- bounded table;
- real pagination;
- obvious primary action.


Do not force the main white panel to fill the entire viewport simply for symmetry.


---


## 87. Detail pages


Prioritize:
- identity/context;
- current status;
- relevant history/data;
- actions.


Do not repeat decorative summary cards that add no decision value.


---


## 88. Workflow pages


Examples:
- imports;
- attendance/scanner;
- referrals;
- tag assignment.


Prioritize:
- current state/step;
- required input;
- validation;
- next action;
- recovery path.


---


# PART XXI — APRISM DOMAIN INTEGRITY


## 89. UI may only imply facts the backend actually knows


Never invent or infer unsupported facts.


Examples:
- do not parse Section to derive Program/Year/Semester;
- do not show a specific class as the cause of a Teacher concern if counts are aggregated across classes;
- do not invent risk levels;
- do not invent grade analytics;
- do not silently autoassign AprilTags;
- Adviser and Program Head are Teacher responsibility extensions, not separate authentication roles;
- Academic Period is not Semester;
- SAE and SCE remain distinct concepts;
- Program Head remains deferred unless scope changes.


Visual clarity never justifies domain inaccuracy.


---


# PART XXII — WHAT TO BORROW FROM THE REFERENCE MATERIAL


## 90. Keep these principles


From the provided reels/slides/examples, selectively use:
- clear section purpose;
- strong visual hierarchy;
- grouping related information;
- 8px-oriented spacing consistency;
- primary CTA distinction;
- meaningful interactive/data cards;
- clear empty states;
- process/file state where real;
- errors shown in context;
- single-column narrow forms;
- plan for small screens;
- helpful whitespace;
- at-a-glance status;
- charts with a clear purpose;
- device-shaped responsive testing.


---


## 91. Do not copy these skins/patterns literally


Do not copy:
- social message/inbox UI;
- likes/comments/follow/share;
- creator branding;
- editorial carousel mechanics;
- oversized marketing typography;
- neon/space aesthetics;
- decorative gradients;
- glassmorphism for decoration;
- fake finance metrics;
- portfolio storytelling;
- consumer upsell patterns;
- giant cards for simple choices;
- excessive animation.


Take the **principle**, not the skin.


---


# PART XXIII — CURRENT APRISM DECISIONS TO PRESERVE


## 92. Existing approved decisions


Unless explicitly revised:
- Technical Admin remains the shared visual baseline;
- generic subtitles are removed when redundant;
- context metadata stays compact;
- toasts remain top-right;
- tables stay bounded and readable;
- mobile avoids horizontal table dragging when card conversion is practical;
- charts must be meaningful, not noisy;
- no page-level horizontal overflow;
- empty states should explain meaning and next action;
- mobile scanner validation remains high priority;
- variable-height rows adapt gracefully;
- desktop header alignment and row-content alignment are separate concerns;
- “maximize white space” is **not** a universal rule.


---


# PART XXIV — RESPONSIVE COMPONENT PATTERNS


## 93. KPI grids


Typical behavior:
- large desktop: up to 4 columns;
- medium: 2 columns where needed;
- mobile: 1–2 columns depending on readability.


Never preserve four tiny cards simply because desktop has four.


---


## 94. Two-panel layouts


- desktop: side-by-side while readable;
- narrower: stack when either panel becomes cramped;
- stacked: natural height.


---


## 95. Filter groups


- desktop: multi-column;
- medium: wrap/reduce;
- mobile: stack logically.


---


## 96. Action groups


- desktop: inline;
- narrow: wrap;
- mobile: stack or keep primary visible + overflow secondary.


---


## 97. Tables


- desktop: table;
- medium: reduce/merge information;
- narrow/mobile: cards where necessary.


---


# PART XXV — FINAL SCREEN REVIEW QUESTIONS


Before approving any page, ask:


## Purpose
- Is the primary task obvious?
- Is the most important information easy to find?


## Hierarchy
- Is the primary action visually dominant?
- Are secondary actions subordinate?
- Are related things grouped?


## Content
- Is wording clear and human?
- Is anything redundant?
- Is every metric/status real?


## Layout
- Is spacing intentional?
- Are there awkward dead zones?
- Is content being stretched unnecessarily?
- Do variable-height blocks align naturally?


## Data views
- Are there unnecessary columns?
- Are related fields grouped?
- Is pagination correct?
- Is there horizontal overflow?


## Forms
- Is field order logical?
- Is validation near the error?
- Are labels clear?


## States
- Does empty/error/loading state explain what is happening?
- Is the next action clear when appropriate?


## Responsive
- Does the layout recompose?
- 1920×1080?
- 1536×864?
- 1440×900?
- 1366×768?
- 1280×720?
- 1280×800?
- 1024×768?
- 768×1024?
- 390×844?
- 430×932?
- Any horizontal page scroll?
- Any pointless vertical scroll?
- Touch targets comfortable?


## Accessibility
- Focus visible?
- Icon buttons named?
- Color not the only cue?
- Text readable?


## Domain integrity
- Does the page imply only facts APRISM actually knows?
- Are permissions preserved?
- Are academic concepts represented correctly?


---


# PART XXVI — REQUIRED CODEX VISUAL WORKFLOW


## 98. Evidence-driven bounded iteration


For meaningful UI/CSS/responsive work, Codex must use this workflow whenever runtime access is available:


1. Render the **current runtime** at the target viewport.
2. Identify a **small set of visible, objective defects**.
3. Implement only those defects.
4. Rerender at the **same viewport**.
5. Compare actual before/after.
6. Correct remaining target defects.
7. Repeat for up to roughly **3 bounded iterations** without broadening scope.
8. Return final runtime evidence and unresolved issues.


Objective checks Codex may verify:
- overflow;
- wrapping;
- collisions;
- alignment;
- widths/heights;
- visibility;
- responsive behavior;
- interactions;
- DOM/state.


Codex must **not** self-certify subjective claims such as:
- “looks perfect”;
- “visually polished”;
- “matches Technical Admin”;
- “ready to freeze.”


Those judgments remain with the project owner/review layer.


If runtime access is unavailable:
- do not broadly redesign from source alone;
- make only safe and clearly justified changes;
- state what remains unverified.


---


# PART XXVII — IMPLEMENTATION DISCIPLINE


## 99. Before editing CSS/UI


Codex must:
- inspect actual DOM/selectors first;
- reuse shared classes/tokens where appropriate;
- avoid duplicate CSS systems;
- avoid unnecessary inline styles;
- protect backend logic and queries;
- never alter domain semantics just to simplify layout;
- never invent fields/states;
- keep changes scoped;
- prefer 2–3 small related changes per pass.


---


# PART XXVIII — FINAL RULE


## 100. The standard


> **APRISM should be intentionally composed at every screen size.**


A strong APRISM screen:
- makes the correct task obvious;
- uses real information;
- shows clear hierarchy;
- groups related content;
- provides feedback near the interaction;
- adapts instead of merely shrinking;
- allows legitimate vertical scrolling;
- avoids horizontal page scrolling;
- stays visually calm;
- does not decorate itself into complexity.


Do not optimize for screenshots.
Optimize for the user performing the real academic task across real devices.


---


## Codex usage note


Before changing any APRISM user-facing screen, read this file together with the relevant project MD/runtime context.


Use a **high reasoning/effort setting** for implementation and responsive QA.


Codex implements and verifies objective behavior. It does **not** independently redefine APRISM's product design.
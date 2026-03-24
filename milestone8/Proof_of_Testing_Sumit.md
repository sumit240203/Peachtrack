# PeachTrack — Milestone 8 Testing Evidence (Front End)

**Tester:** Sumit Niveriya  
**Application:** PeachTrack (PHP/MySQL)  
**Test environment:** Chrome (desktop) via ngrok URL provided by team  
**Scope (my part):** Front-end/UI testing of main website screens, forms, navigation, and report/payroll UI.

## What I tested (high-level)
- Menu/navigation between all major screens (Employee + Admin/Manager)
- Employee shift workflow: start shift → log tips (cash/electronic) → end shift
- Form labels, inputs, and feedback messages
- Tables and report pages rendering, including empty-state messaging
- Payroll totals display and CSV export link

## Evidence (screenshots)
Screenshots are stored here:
`peachtrack-shift-tip-dashboard/milestone8/testing-evidence/screenshots/`

| # | Screenshot file | What it proves |
|---|---|---|
| 01 | 01_admin_dashboard.png | Admin menu + dashboard layout + consistent styling |
| 02 | 02_reports_fullpage.png | Reports page layout, filters, charts area, empty-state messaging |
| 03 | 03_employee_dashboard_log_tip.png | Employee dashboard: shift card + Log Tip form with meaningful labels |
| 04 | 04_employee_shift_started.png | Start Shift changes UI to Active state + timer |
| 05 | 05_employee_tip_submitted_cash.png | Tip submitted (Cash) + recent tips row + totals update |
| 06 | 06_employee_tip_submitted_electronic.png | Tip submitted (Electronic) + recent tips row + totals update |
| 07 | 07_employee_my_shifts_with_charts.png | My Shifts: charts render + summary cards reflect new data |
| 08 | 08_employee_shift_ended_summary.png | End Shift summary message + returns to Not started |
| 09 | 09_admin_dashboard_updated_totals.png | Admin dashboard totals updated after employee activity |
| 10 | 10_admin_payroll_totals_unpaid.png | Payroll totals show cash/electronic breakdown + Export CSV option |

## Test cases executed (summary)
Detailed steps are documented in the Excel test plan:
`milestone8/PeachTrack_M8_TestPlan_Sumit.xlsx`

Executed key end-to-end flow:
1) Start shift (Employee) → verified Active status + duration timer.
2) Log tip (Cash) → verified success message + new row in Recent Tips.
3) Log tip (Electronic) → verified success message + method shown.
4) End shift → verified summary includes totals + breakdown.
5) Check My Shifts → verified shift appears and charts render.
6) Check Admin dashboard → verified tips/sales summary values updated.
7) Check Payroll → verified totals and cash/electronic split + export option.

## Findings / issues
- No blocking UI bugs were observed during the executed flow.
- Empty state on Reports (“No data for this range.”) appears clear and user-friendly.

## Testing confidence
**High confidence** for the tested front-end workflow because:
- The main business workflow (shift → tips → end shift) was executed end-to-end.
- Multiple payment types were tested (Cash + Electronic).
- Cross-checks were performed on downstream views (My Shifts charts, Admin dashboard totals, Payroll totals).

## Out of scope (not tested by me)
- Backend/database logic implementation details
- Security hardening beyond basic UI behavior (e.g., SQL injection testing)
- Installation testing on the client lab computer (requires in-lab environment)

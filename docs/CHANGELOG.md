# Changelogs

Release v1.0.0-beta.4 (TBA)
- Fix: Original order capture for sorting modules

Release v1.0.0-beta.3 (2/27/2026)
- Fix: Search field, category filter, and sort order now update the module list automatically without requiring the search button to be clicked
- Fix: Category filter and sort dropdown were triggering a full page reload via a competing inline script; inline script removed and all three controls unified under a single ActiveForm
- Fix: Search input was isolated in its own separate ActiveForm instance, disconnecting it from the category and sort controls; all three controls now share a single form
- Enh: Module list filters entirely client-side with no page reload; sort re-orders visible cards in place

Release v1.0.0-beta.2 (2/26/2026)
- Fix: "Clear cache" action shows error first before success message; now only success message appears correctly for both AJAX and full-page requests
- Fix: Category filter and sort order not working in Bazaar module

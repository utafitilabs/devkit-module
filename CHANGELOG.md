# Changelog

## Unreleased (0.3)

- The Modules surface carries no summary strip. Its registry sits under the
  house filter bar (an "on core" chip with counts, and a search) and over the
  Shell's register foot, `@Shell/_foot.html.twig` paged by `shell.table_page`
  (the count, 20 · 50 · 100 · All per page, the page; `?page=` and `?per=`,
  `?mpage=` and `?mper=` for the per-area table); the columns
  read "permissions" and "is the core", and a module on in every area says so.
- The per-area module state is drawn: a house table, one row per module and
  one column per area, the modules switched on per area first, each row's
  sub-line saying "per area · on in n of m areas" or "every area" — no group
  rows. It has its own bar (a reach chip and a search) and foot. The state is
  read off the registry's ledger through `AreaStatesInterface`
  (`RegistryAreaStates`, registered only where RegistryBundle and Doctrine are
  installed); without one the surface says so.

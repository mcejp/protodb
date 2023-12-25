# protodb DRC

### Terminology

- check = algorithm that can be applied to a certain class of objects
	- produces incidents of 1 or more violation type(s)
	- runs within a DRC context (but that's an implementation detail)
- DRC context
- incident/violation (violation type)

### Notes

- It is difficult to generally assign some *ownership* to DRC incidents (consider for example collisions among two
  entities). This could be useful for re-checking only a part of the database (e.g. an ECU). For now, an entire package
  must be always re-checked.
  
  Some sort of dependency graph might yield a general solution, but it is only worth it if re-checking a package takes
  a long time.
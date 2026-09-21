import * as React from "react"

const MOBILE_BREAKPOINT = 768
const MOBILE_QUERY = `(max-width: ${MOBILE_BREAKPOINT - 1}px)`

function subscribe(onStoreChange: () => void) {
  const mql = window.matchMedia(MOBILE_QUERY)
  mql.addEventListener("change", onStoreChange)
  return () => mql.removeEventListener("change", onStoreChange)
}

function getSnapshot() {
  return window.innerWidth < MOBILE_BREAKPOINT
}

// En servidor y durante la hidratación no hay viewport que medir. `false` es el
// mismo valor que devolvía la versión anterior en el primer render (partía de
// `undefined` y lo pasaba por `!!`), así que el marcado hidratado no cambia.
function getServerSnapshot() {
  return false
}

// matchMedia es un sistema externo, que es justo el caso de useSyncExternalStore:
// evita el setState dentro del efecto (y el render en cascada que provocaba) en
// lugar de silenciar la regla.
export function useIsMobile() {
  return React.useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot)
}

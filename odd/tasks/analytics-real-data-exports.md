# Analíticas reales y exportaciones

## Objetivo y autorización
El usuario solicita reemplazar datos dummy en /analytics/groups, /analytics/campaigns, /analytics/cities y /analytics/devices, habilitar exportación PDF o Excel en /analytics y agregar exportación en /audit. Implementación local autorizada en E:/programacion/dashboard-postobon; el cwd inicial dashboard-fan-wl35 no contiene estas rutas. Preservar docs/DESPLIEGUE_LATENCIA.md y cualquier trabajo ajeno. Sin despliegue, push, PR, migraciones, acceso a producción ni comandos de dispositivos.

## Evidencia, alcance y decisiones
El mapa delegado map_analytics_audit verificó listas/rand/gráficas ficticias en las cuatro vistas, consultas obsoletas de locations y status=active, consultas inline y fechas inertes en el resumen. Las rutas están protegidas para superadmin. Auditoría ya tiene filtros reales y paginación.

Usar registros locales existentes: inventario mixto 42/35 con identidad normalizada sin duplicar perfiles; estado guardado y métricas disponibles con etiquetas precisas. No convertir disponibilidad actual en uptime histórico ni horas acumuladas en horas de un período. WL35 sin telemetría persistida se presenta como no disponible. Ciudades desde campo actual, con fallback histórico y Sin ciudad; Establishment no tiene campo ciudad y no se infiere de su dirección. Grupos/campañas por identidad, no nombre. Estadísticas de campañas son registros persistidos, no mediciones inventadas; sin filas mostrar ausencia de estadísticas. No borrar ni inferir origen de históricos anteriores. Desactivar únicamente la generación normal de estadísticas dummy si el seeder actual la incorpora.

Exportaciones descargables PDF y Excel XLSX reales, sin servicios externos. Reutilizar un exportador tabular local, conservar caracteres españoles, números calculables y textos como celdas de texto para impedir fórmulas. Mismo alcance y métricas que la pantalla; auditoría exporta todos los resultados de los filtros, no sólo la página. Retirar fechas inertes del resumen y describir alcance acumulado actual. Conservar diseño existente y controles accesibles. Preparación delegada prepare_export encontró Dompdf3.1.6 y todas sus dependencias en caché local: instalar offline con red deshabilitada, sin scripts ni cambios a dependencias previas; XLSX mediante ZipArchive/OpenXML. PDF usa fuente DejaVu, recursos remotos/PHP/JS deshabilitados y bloques paginables para textos largos.

## Flujo, pruebas y entrega
- Ruta delegada directa: mapa requiere más de cuatro archivos; cada tarea tiene más de dos archivos no triviales. Trabajadores leen este documento antes de editar. Padre integra, verifica y realiza commits por unidad; sin SDD.
- TDD OFF: decisión explícita previa del usuario 2026-10-10, corroborada en odd/tasks/group-membership-audit.md y observación Engram 6. Runner exacto: php artisan test --compact --bootstrap=storage/framework/testing/isolated-suite-bootstrap.php. Bootstrap a SQLite :memory: y HTTP bloqueado; no DB configurada ni nube.
- RDD disabled/unmanaged: gentle-ai review mode status confirmó OFF global en este repositorio. No iniciar revisión nativa.
- Checks: PHPUnit enfocado por comportamiento/permisos, Pint de PHP cambiado, npm run build, git diff --check; Node sólo si cambia comportamiento JS. Verificación de estructura/lectura real de PDF y XLSX; comprobación visual local aislada si aporta evidencia. Registrar fallos y límites sin inventar pruebas de producción.
- Delivery ask-on-risk con elección previa feature-branch-chain retenida de documentos/Engram del mismo proyecto. Rama local codex/analytics-real-data-exports desde cf51812303df5db5518930ddfca07ebc85a4d7b8. No crear PR ni publicar. Forecast 900–1550 líneas authored, generados excluidos; tres slices coherentes T1/T2/T3. Heurística 400 por tarea orientativa: no compactar, quitar tests ni dividir artificialmente; conservar cada comportamiento completo y reportar excesos. Los commits definen límites de slices futuros.
- Engram proyecto dashboard-postobon; topic odd/analytics-real-data-exports/tasks. Mirror completo con locator odd/tasks/analytics-real-data-exports.md; lectura de vuelta antes de fuente y después de cada tarea. Observación15 inicial leída íntegra y comparada con archivo antes de fuente.

## Tareas
- [x] T1 — Sustituir analíticas ficticias por registros reales.
  - Ruta delegada: servicio/controlador/cinco vistas/pruebas no triviales; preparación y mapping delegados.
  - Aceptación: valores exactos del censo 42/35 sin duplicados, grupos y ciudades reales incluidos vacíos/sin ciudad, campañas con/sin estadísticas, estado y horas etiquetados correctamente, sin rand/listas de ejemplo/consultas Blade. No HTTP remoto.
  - Checks: fixtures locales con valores diferenciables, vacíos, nombres duplicados, mezcla 42/35, estadísticas y roles; tests existentes de snapshot/roles relevantes; Pint/build.
  - Rollback: servicio/controlador/vistas de analítica y regresiones de datos; sin alterar inventario ni históricos.
  - Implementado: report() compartido con inventario42/35 normalizado/deduplicado, grupos vacíos/sin grupo, ciudad directa/fallback local, estadísticas de campañas no eliminadas y cobertura de horas explícita. Vistas reales sin consultas inline/rand/listas inventadas; errores visibles en vez de falsos ceros. Sin duración de campaña: unidad no documentada. API y consumidor DashboardService conservan claves y usan un reporte por consulta. Seeder normal deja de invocar CampaignStatisticSeeder, preservando históricos.
  - Observado: php artisan test --compact --bootstrap=storage/framework/testing/isolated-suite-bootstrap.php --filter='AnalyticsRealDataTest|AuditPresentationTest|DashboardSnapshotTest|RetiredModulesTest|UserRoleAccessTest|RestrictedRoleLayoutTest' pasó64tests/824assertions en8.95s. Pint6PHP pasó; npm run build pasó69módulos/11.01s; git diff --check pasó. Fallos iniciales de fixtures (grupo UNIQUE y guard Sanctum) corregidos, sin fallo pendiente. Padre inspeccionó servicio/controlador, tests, consumidores, seeder y vistas compartidas.
  - Runtime: Blade/controladores reales renderizados por pruebas HTTP aisladas; browser separado no ejecutado. Sin producción/hardware/nube. No existe productor operativo de estadísticas encontrado; ausencia de mediciones se muestra honestamente.
  - Commit pendiente de creación por padre; slice T1 es unidad coherente que excede400 por reemplazo de cinco vistas dummy, servicio y regresiones. No se recorta por tamaño; feature-branch-chain retenida. RDD disabled/unmanaged.
- [ ] T2 — Descargar analítica en PDF o XLSX.
  - Ruta delegada: renderer/request/controlador/ruta/selector/pruebas, múltiples archivos no triviales.
  - Aceptación: selector accesible de ambos formatos, descargas válidas con datos reales equivalentes al resumen, filas largas/múltiples páginas, acentos, nombres maliciosos tratados como texto, formato inválido rechazado, acceso restringido y errores tratados.
  - Checks: estructura y contenido PDF/XLSX, consulta vacía y varias páginas, permisos/validación, regresiones de T1, Pint/build y prueba local del flujo de descarga cuando posible.
  - Rollback: exportador y endpoint/UI de exportación; analíticas reales siguen funcionando.
  - Evidencia y commit: pendientes.
- [ ] T3 — Exportar auditoría con los filtros vigentes.
  - Ruta delegada: consulta compartida/controlador/request/ruta/vista/pruebas no triviales.
  - Aceptación: botón con PDF/XLSX, mismos filtros/orden/fechas/permisos que /audit, todos los resultados sin paginar, usuarios ausentes/JSON/acentos escapados correctamente, lista vacía honesta. No ampliar instrumentación de eventos.
  - Checks: filtros combinados, rango fechas, más de una página, roles, JSON/escaping y archivos; AuditPresentationTest actualizado; comprobación integrada focalizada, Pint/build/diff.
  - Rollback: exportación de auditoría y extracción de consulta, sin tocar registros ni productores.
  - Evidencia y commit: pendientes.

## Progreso y siguiente paso
T1 verificada y pendiente commit local. T2 avanzó en archivos separados (exportador/dependencias/pruebas) mientras terminaban vistas T1; conexión Web controller/index compartida se integra con staging selectivo sin mezclar commits. Siguiente: cerrar commit T1, verificar T2 y conectar auditoría T3.

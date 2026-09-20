/**
 * Placeholder hasta que `Project` tenga un campo `description` real en el
 * backend. Cíclico por índice, igual que avatarToneFor, para que las cards
 * del dashboard no se vean todas idénticas mientras tanto.
 */
const MOCK_DESCRIPTIONS: string[] = [
  "Aplicación de reservas en tiempo real con panel de gestión para el equipo y notificaciones automáticas para los usuarios.",
  "Plataforma de matching entre voluntarios y organizaciones locales, con seguimiento de horas y certificados descargables.",
  "Editor colaborativo de documentos con control de versiones, comentarios en línea y exportación a varios formatos.",
  "API de recomendaciones basada en el historial de compras, pensada para integrarse en tiendas online existentes.",
  "Dashboard de métricas de producto con alertas configurables y exportación de informes en PDF.",
  "Bot de soporte que responde preguntas frecuentes y escala a un humano cuando no encuentra respuesta.",
];

export function mockProjectDescription(index: number): string {
  return MOCK_DESCRIPTIONS[index % MOCK_DESCRIPTIONS.length];
}

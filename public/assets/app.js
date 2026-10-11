"use strict";
const config = window.EXPLORANDO_CONTACTO || {};
const correo = typeof config.correo === "string" && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(config.correo) ? config.correo : "";
const whatsapp = typeof config.whatsapp === "string" && /^[1-9]\d{7,14}$/.test(config.whatsapp) ? config.whatsapp : "";
const state = document.querySelector("#contact-state");
const direct = document.querySelector("#direct-contact");
function link(label, href) { const a = document.createElement("a"); a.textContent = label; a.href = href; a.className = "text-link"; direct.append(a); }
if (correo || whatsapp) {
 state.textContent = "Conversemos sobre tu propuesta. Puedes escribirnos directamente o preparar tu mensaje.";
 if (correo) link("Escríbenos por correo ↗", "mailto:" + correo);
 if (whatsapp) link("Conversemos en WhatsApp ↗", "https://wa.me/" + whatsapp);
}
document.querySelectorAll("[data-category]").forEach(a => a.addEventListener("click", () => { document.querySelector("#tipo").value = a.dataset.category; }));
const form = document.querySelector("#ally-form");
form.addEventListener("submit", event => {
 event.preventDefault();
 const data = new FormData(form);
 const msg = `Hola, Explorando Lugares. Quiero presentar mi propuesta como aliado.\n\nNombre: ${data.get("nombre").trim()}\nNegocio: ${data.get("negocio").trim()}\nCategoría: ${data.get("tipo")}\nDestino: ${data.get("destino").trim()}\n\n${data.get("mensaje").trim()}`;
 document.querySelector("#message-preview").value = msg;
 document.querySelector("#message-result").hidden = false;
 const mail = document.querySelector("#send-mail"), wa = document.querySelector("#send-wa");
 if(correo) { mail.href = "mailto:" + correo + "?subject=" + encodeURIComponent("Propuesta de aliado — Explorando Lugares") + "&body=" + encodeURIComponent(msg); mail.hidden = false; }
 if(whatsapp) { wa.href = "https://wa.me/" + whatsapp + "?text=" + encodeURIComponent(msg); wa.hidden = false; }
 document.querySelector("#form-status").textContent = "Mensaje preparado. Todavía no se ha enviado.";
});
document.querySelector("#copy-message").addEventListener("click", async () => {
 const preview = document.querySelector("#message-preview");
 try { await navigator.clipboard.writeText(preview.value); document.querySelector("#form-status").textContent = "Mensaje copiado. Compártelo por el canal de tu preferencia."; }
 catch { preview.focus(); preview.select(); document.querySelector("#form-status").textContent = "Seleccionamos el mensaje. Usa Copiar en tu dispositivo para copiarlo manualmente."; }
});

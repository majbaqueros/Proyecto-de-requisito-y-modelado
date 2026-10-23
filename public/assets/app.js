'use strict';
const app = document.getElementById('app');
const toast = document.getElementById('toast');
const state = { user:null, csrf:null, profileId:null, role:'paciente', view:'dashboard', busy:false, unread:0 };
const roles = {
  paciente:{label:'Paciente',emoji:'🧑‍⚕️',description:'Mis citas y mi información'},
  recepcionista:{label:'Recepcionista',emoji:'🗓️',description:'Agenda y atención al público'},
  medico:{label:'Médico',emoji:'🩺',description:'Consultas e historias clínicas'},
  administrador:{label:'Administrador',emoji:'🛡️',description:'Configuración de la clínica'}
};
// Cada rol puede abrirse con una URL propia: /paciente, /medico, etc.
const urlRole = location.pathname.replace(/^\//, '').replace(/\/$/, '');
if (Object.prototype.hasOwnProperty.call(roles, urlRole)) state.role = urlRole;
const demo = {
  paciente:['paciente@clinica.local','Paciente123*'],
  recepcionista:['recepcion@clinica.local','Recep123*'],
  medico:['medico@clinica.local','Medico123*'],
  administrador:['admin@clinica.local','Admin123*']
};
const sections = {
  dashboard:['📊','Resumen'], appointments:['📅','Citas médicas'], doctors:['👨‍⚕️','Médicos'],
  patients:['👥','Pacientes'], records:['🗂️','Historias clínicas'], invoices:['🧾','Facturación'],
  schedules:['🕘','Horarios'], specialties:['⚕️','Especialidades'],users:['🔐','Usuarios'],notifications:['🔔','Notificaciones']
};
const navByRole = {
  paciente:['dashboard','appointments','doctors','records','invoices','notifications'],
  recepcionista:['dashboard','appointments','patients','doctors','invoices','notifications'],
  medico:['dashboard','appointments','records','doctors','notifications'],
  administrador:['dashboard','appointments','patients','doctors','schedules','specialties','invoices','users','notifications']
};
const days=['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
const escapeHtml = value => String(value??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => 'Bs '+Number(n||0).toFixed(2);
const fmtDate = s => s ? String(s).replace('T',' ').slice(0,16) : '—';
const badge = s => `<span class="badge ${escapeHtml(s)}">${escapeHtml(s)}</span>`;
const empty = (message='Todavía no hay registros') => `<div class="empty"><div class="ico">🗒️</div><b>Sin resultados</b>${escapeHtml(message)}</div>`;
const input = (label,name,type='text',opts={}) => `<div class="field ${opts.wide?'wide':''}"><label for="f-${escapeHtml(name)}">${label}</label><input id="f-${escapeHtml(name)}" type="${type}" name="${escapeHtml(name)}" ${opts.required===false?'':'required'} ${opts.min!==undefined?`min="${opts.min}"`:''} ${opts.step!==undefined?`step="${opts.step}"`:''} ${opts.maxlength?`maxlength="${opts.maxlength}"`:''} placeholder="${escapeHtml(opts.placeholder||'')}"></div>`;
const select = (label,name,options,opts={}) => `<div class="field ${opts.wide?'wide':''}"><label for="f-${escapeHtml(name)}">${label}</label><select id="f-${escapeHtml(name)}" name="${escapeHtml(name)}" required><option value="">Seleccionar...</option>${options.map(o=>`<option value="${escapeHtml(o.value)}">${escapeHtml(o.label)}</option>`).join('')}</select></div>`;
const textarea = (label,name,placeholder='') => `<div class="field wide"><label for="f-${name}">${label}</label><textarea id="f-${name}" name="${name}" required placeholder="${escapeHtml(placeholder)}"></textarea></div>`;
const panel = (title,content,cls='') => `<section class="panel ${cls}"><h2 class="section-heading">${title}</h2>${content}</section>`;
const table = (heads,rows) => !rows.length ? empty('Los datos aparecerán aquí cuando se registren.') : `<div class="table-scroll"><table class="data-table"><thead><tr>${heads.map(h=>`<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table></div>`;
const opts = (items,label='name') => items.map(x=>({value:x.id,label:x[label]}));
const head = (title,subtitle,action='') => `<div class="page-heading"><div><span class="eyebrow">SALUDCITA · GESTIÓN CLÍNICA</span><h1>${title}</h1><p>${subtitle}</p></div>${action}</div>`;
const form = (action,fields,submit='Guardar') => `<form data-form="${action}"><div class="form-grid">${fields}</div><button class="primary" type="submit">＋ ${submit}</button></form>`;

async function api(url,options={}) {
  const cfg={method:options.method||'GET',headers:{'Accept':'application/json'},credentials:'same-origin'};
  if (options.body!==undefined){ cfg.headers['Content-Type']='application/json'; cfg.body=JSON.stringify(options.body); }
  if (cfg.method!=='GET'&&url!=='/api/auth/login') cfg.headers['X-CSRF-Token']=state.csrf||'';
  let response;
  try{response=await fetch(url,cfg);}catch{throw new Error('No se puede conectar al servidor. ¿Están funcionando PHP y PostgreSQL?');}
  const data=await response.json().catch(()=>({error:'Respuesta no válida del servidor.'}));
  if(!response.ok){
    if(response.status===401 && !['/api/auth/login','/api/auth/register'].includes(url)){state.user=null;loginScreen();}
    throw new Error(data.error||`Error HTTP ${response.status}`);
  }
  return data;
}
let toastTimer;
function flash(text,error=false){toast.textContent=text;toast.className='toast show'+(error?' error':'');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.classList.remove('show'),4000);}
function loginScreen(){
  const role=roles[state.role];
  app.innerHTML=`<div class="login-layout"><aside class="login-art"><div class="brand"><div class="brandmark">✚</div>SaludCita</div><div class="login-copy"><div class="eyebrow">PROYECTO UNIVERSITARIO</div><h1>Una mejor forma de cuidar cada cita.</h1><p>Un sistema completo de gestión de citas médicas, con acceso seguro por roles y componentes independientes construidos con principios SOLID.</p><div class="feature"><b>✓</b>Agenda y disponibilidad médica</div><div class="feature"><b>✓</b>Consultas e historias clínicas</div><div class="feature"><b>✓</b>Facturación y notificaciones</div></div><div class="login-foot">Ingeniería de Requisitos y Modelado · Demo académica</div></aside>
  <main class="login-right"><div class="login-panel"><div class="eyebrow">ACCESO AL SISTEMA</div><h2>Bienvenido/a</h2><p class="sub">Selecciona tu perfil e inicia sesión para entrar a tu espacio de trabajo.</p><div class="role-choices">${Object.entries(roles).map(([id,r])=>`<button class="role-card ${id===state.role?'active':''}" type="button" data-role="${id}"><span class="ico">${r.emoji}</span><span><strong>${r.label}</strong><small>${r.description}</small></span></button>`).join('')}</div><form data-form="login"><div class="field"><label for="login-email">Correo electrónico</label><input id="login-email" name="email" type="email" placeholder="nombre@clinica.com" autocomplete="username" required></div><div class="field"><label for="login-password">Contraseña</label><input id="login-password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required></div><button type="submit" class="primary full">Entrar como ${role.label} →</button></form>${state.role==='paciente'?`<div class="register-prompt">¿Es tu primera vez? <button type="button" class="text-link" data-show-register>Crear cuenta de paciente</button></div>`:''}<div class="demo-box"><div class="demo-title">🔑 Acceso de demostración</div><p>Las credenciales son únicamente para pruebas locales. No usar en producción.</p><div class="demo-row"><code>${escapeHtml(demo[state.role][0])}</code><button type="button" class="secondary small" data-demo>Usar demo</button></div></div></div></main></div>`;
}
function registerScreen() {
  state.role='paciente';history.replaceState({},'','/paciente');
  app.innerHTML=`<div class="login-layout"><aside class="login-art"><div class="brand"><div class="brandmark">✚</div>SaludCita</div><div class="login-copy"><div class="eyebrow">PACIENTES</div><h1>Tu salud empieza con una cita.</h1><p>Crea tu cuenta para solicitar turnos médicos, consultar tus reservas y recibir notificaciones.</p><div class="feature"><b>✓</b>Registro de paciente</div><div class="feature"><b>✓</b>Agenda médica</div><div class="feature"><b>✓</b>Seguimiento de citas</div></div><div class="login-foot">Sistema universitario de demostración · No usar datos reales</div></aside>
  <main class="login-right"><div class="login-panel"><div class="eyebrow">NUEVO PACIENTE</div><h2>Crear cuenta</h2><p class="sub">El registro público solo permite crear cuentas de pacientes.</p><form data-form="register"><div class="field"><label>Nombre completo</label><input name="name" required minlength="3" maxlength="120" autocomplete="name"></div><div class="field"><label>Correo electrónico</label><input name="email" type="email" required autocomplete="email"></div><div class="field"><label>Documento / CI</label><input name="document" required minlength="4" maxlength="30"></div><div class="field"><label>Contraseña (mín. 8 caracteres)</label><input name="password" type="password" minlength="8" required autocomplete="new-password"></div><div class="field"><label>Teléfono (opcional)</label><input name="phone" type="tel"></div><button type="submit" class="primary full">Crear mi cuenta</button></form><div class="register-prompt">¿Ya tienes cuenta? <button class="text-link" data-back-login>Iniciar sesión</button></div></div></main></div>`;
}

async function toggleNotifications() {
  const pop=document.getElementById('notifier-popover');
  if(!pop) return;
  if(!pop.hidden){pop.hidden=true;return;}
  pop.hidden=false;
  pop.innerHTML='<p class="popup-loading">Cargando notificaciones...</p>';
  try {
    const items=await api('/api/notifications');
    state.unread=items.filter(n=>!n.read_at).length;
    const count=document.getElementById('unread-count');
    if(count){count.textContent=state.unread?String(state.unread):'';count.hidden=state.unread===0;}
    pop.innerHTML=`<div class="popup-head"><strong>Notificaciones</strong><button class="text-link" data-view="notifications">Ver todas</button></div>${items.length?items.slice(0,5).map(n=>`<div class="popup-note ${n.read_at?'':'unread'}"><b>${escapeHtml(n.title)}</b><small>${escapeHtml(n.message)}</small>${n.read_at?'':`<button class="text-link" data-read="${Number(n.id)}">Marcar leída</button>`}</div>`).join(''):'<p class="popup-loading">No tienes notificaciones.</p>'}`;
  }catch(e){pop.textContent=e.message;}
}
async function refreshBell() {
  const count=document.getElementById('unread-count');
  if(!count)return;
  try{const items=await api('/api/notifications');state.unread=items.filter(n=>!n.read_at).length;count.textContent=state.unread?String(state.unread):'';count.hidden=state.unread===0;}catch{}
}

function shell(){
  const r=roles[state.user.role];
  const nav=navByRole[state.user.role];
  app.innerHTML=`<div class="layout"><aside class="sidebar"><div class="brand"><div class="brandmark">✚</div>SaludCita</div><div class="sidebar-label">MENÚ PRINCIPAL</div><nav class="nav-menu" aria-label="Navegación">${nav.map(k=>`<button class="nav-link ${k===state.view?'active':''}" data-view="${k}"><span class="ico">${sections[k][0]}</span>${sections[k][1]}</button>`).join('')}</nav><div class="sidebar-bottom"><div class="avatar">${escapeHtml(state.user.name.charAt(0).toUpperCase())}</div><div><strong>${escapeHtml(state.user.name)}</strong><span>${r.label}</span></div></div><button class="sidebar-logout" data-logout>↪ Cerrar sesión</button></aside><div class="content-wrap"><header class="topbar"><div><span class="hello">Plataforma de atención médica</span><br><strong>${escapeHtml(state.user.name)}</strong></div><div class="topbar-right"><div class="notif-host"><button type="button" class="ghost small bell-button" data-bell title="Notificaciones" aria-label="Abrir notificaciones">🔔 <span id="unread-count" class="unread-count" hidden></span></button><div id="notifier-popover" class="notification-popover" hidden></div></div><span class="role-pill">${r.emoji} ${r.label}</span><button class="ghost small" data-logout title="Salir">Salir ↗</button></div></header><main class="page" id="page"><div class="boot" style="height:50vh"><div class="spinner"></div></div></main></div></div>`;
}
async function go(view){
  if(!navByRole[state.user.role].includes(view)) return;
  state.view=view; shell();
  const target=document.getElementById('page');
  try{
    const views={dashboard:dashboardView,appointments:appointmentsView,doctors:doctorsView,patients:patientsView,records:recordsView,invoices:invoicesView,schedules:schedulesView,specialties:specialtiesView,users:usersView,notifications:notificationsView};
    const html=await views[view]();
    if(state.view===view&&document.getElementById('page')===target){ target.innerHTML=html; refreshBell(); }
  }catch(e){if(document.getElementById('page')===target){target.innerHTML=panel('No se pudo cargar',`<p>${escapeHtml(e.message)}</p><button class="secondary" data-view="${view}">Reintentar</button>`);flash(e.message,true);}}
}
function statusButtons(a){
  const role=state.user.role, id=Number(a.id), buttons=[];
  if(['administrador','recepcionista'].includes(role) && a.status==='pendiente')buttons.push(`<button class="secondary small" data-status="confirmada" data-id="${id}">Confirmar</button>`);
  if(['administrador','recepcionista','paciente'].includes(role) && ['pendiente','confirmada'].includes(a.status))buttons.push(`<button class="danger small" data-status="cancelada" data-id="${id}">Cancelar</button>`);
  if(role==='medico'&&a.status==='confirmada')buttons.push(`<button class="secondary small" data-status="completada" data-id="${id}">Completar</button>`);
  return buttons.join('')||'<span style="color:#b2bec8">—</span>';
}
async function dashboardView(){
  const data=await api('/api/dashboard');
  const c=data.counts;
  const stats=[['📅','Citas registradas',c.total],['⏳','Pendientes',c.pendientes],['✔','Confirmadas',c.confirmadas],['🩺','Completadas',c.completadas]];
  const rows=data.recent.map(a=>`<tr><td class="name">#${a.id}</td><td>${fmtDate(a.scheduled_at)}</td><td>${escapeHtml(state.user.role==='paciente'?a.doctor_name:a.patient_name)}</td><td>${badge(a.status)}</td></tr>`);
  const greeting=state.user.role==='medico'?'Revisa tu agenda y registra las consultas realizadas.':state.user.role==='paciente'?'Organiza tus próximas consultas y revisa su estado.':state.user.role==='recepcionista'?'Organiza las citas y registra la atención a pacientes.':'Supervisa la clínica, su personal y sus operaciones.';
  return head('Panel de control',`Hola, ${escapeHtml(state.user.name)}. ${greeting}`)+
    `<div class="grid-stats">${stats.map(s=>`<div class="stat-card"><div class="s-icon">${s[0]}</div><div class="stat-label">${s[1]}</div><div class="stat-value">${Number(s[2])}</div></div>`).join('')}</div>`+
    `<div class="banner"><div><h3>Tu espacio de atención médica ✨</h3><p>Gestiona la información desde un solo lugar. Cada rol dispone únicamente de las funciones que le corresponden.</p></div><button class="secondary" data-view="appointments">Ver citas →</button></div>`+
    panel('Actividad reciente',table(['Cita','Fecha','Participante','Estado'],rows))+
    `<p class="footer-note">Tienes ${data.unread} notificación(es) sin leer. Plataforma académica; no utilizar con datos médicos reales sin controles adicionales.</p>`;
}
async function appointmentsView(){
  const [items,docs,schedules]=await Promise.all([api('/api/appointments'),api('/api/doctors'),api('/api/schedules')]);
  let registration='';
  if(state.user.role!=='medico'){
    const patients=state.user.role==='paciente'?[]:await api('/api/patients');
    registration=panel('Agendar nueva cita',form('appointment',
      (state.user.role==='paciente'?'':select('Paciente','patient_id',opts(patients)))+
      select('Médico','doctor_id',docs.map(d=>({value:d.id,label:`${d.name} · ${d.specialty}`})))+
      input('Fecha y hora','scheduled_at','datetime-local')+
      textarea('Motivo de la consulta','reason','Ej. Control médico general'), 'Agendar cita')+
      `<p class="sub" style="font-size:.76rem;margin-top:15px">Duración: 30 minutos. Selecciona una hora dentro del horario del médico y con minutos 00 o 30. El sistema valida disponibilidad.</p>`);
  }
  const rows=items.map(a=>`<tr><td class="name">#${a.id}</td><td>${fmtDate(a.scheduled_at)}</td><td>${escapeHtml(a.patient_name)}</td><td>${escapeHtml(a.doctor_name)}<br><small>${escapeHtml(a.specialty)}</small></td><td title="${escapeHtml(a.reason)}">${escapeHtml(a.reason.slice(0,45))}</td><td>${badge(a.status)}</td><td><div class="actions">${statusButtons(a)}</div></td></tr>`);
  const appointmentTable=panel(state.user.role==='medico'?'Mi agenda médica':'Listado de citas',table(['N°','Fecha','Paciente','Médico','Motivo','Estado','Acciones'],rows));
  const scheduletxt=`<details class="panel"><summary style="cursor:pointer;font-weight:800">🕘 Ver horarios de atención disponibles</summary><div class="table-scroll" style="margin-top:15px">${table(['Médico','Día','Desde','Hasta'],schedules.map(s=>`<tr><td>${escapeHtml(s.doctor_name)}</td><td>${days[Number(s.weekday)]}</td><td>${String(s.start_time).slice(0,5)}</td><td>${String(s.end_time).slice(0,5)}</td></tr>`))}</div></details>`;
  return head('Gestión de citas',state.user.role==='medico'?'Consulta tu agenda y registra el estado de las atenciones.':'Registra, confirma o cancela citas según tus permisos.')+
  (registration?`<div class="split"><div>${registration}</div><div>${appointmentTable}</div></div>`:appointmentTable)+scheduletxt;
}
async function patientsView(){
  const patients=await api('/api/patients');
  const left=panel('Registrar paciente',form('patient',input('Nombre completo','name')+input('Correo','email','email')+
    input('Contraseña inicial','password','password',{min:8})+input('Documento / CI','document')+
    input('Fecha de nacimiento','birth_date','date',{required:false})+input('Teléfono','phone','tel',{required:false})+
    input('Dirección','address','text',{required:false,wide:true}),'Registrar paciente'));
  const rows=patients.map(x=>`<tr><td class="name">${escapeHtml(x.name)}</td><td>${escapeHtml(x.document)}</td><td>${escapeHtml(x.email)}</td><td>${escapeHtml(x.phone||'—')}</td><td>${escapeHtml(x.birth_date||'—')}</td></tr>`);
  return head('Pacientes','Datos básicos y registro de nuevos pacientes.')+`<div class="split"><div>${left}</div>${panel('Directorio de pacientes',table(['Nombre','Documento','Correo','Teléfono','Nacimiento'],rows))}</div>`;
}
async function doctorsView(){
  const [docs,sched,spec]=await Promise.all([api('/api/doctors'),api('/api/schedules'),api('/api/specialties')]);
  const cards=docs.map(d=>`<article class="doctor-card"><div class="doctor-top"><div class="doctor-avatar">🩺</div><div><h3>${escapeHtml(d.name)}</h3><p>${escapeHtml(d.specialty)}</p></div></div><div class="doc-price">${money(d.consultation_price)} <small>/ consulta</small></div><p class="sub" style="font-size:.77rem">${escapeHtml(d.email)}</p><div class="schedule-tags">${sched.filter(s=>Number(s.doctor_id)===Number(d.id)).map(s=>`<span class="schedule-tag">${days[Number(s.weekday)].slice(0,3)} ${String(s.start_time).slice(0,5)}-${String(s.end_time).slice(0,5)}</span>`).join('')||'<small>Sin horarios</small>'}</div></article>`).join('');
  const creator=state.user.role==='administrador'?panel('Agregar médico',form('doctor',input('Nombre completo','name')+input('Correo','email','email')+input('Contraseña inicial','password','password')+select('Especialidad','specialty_id',opts(spec))+input('Costo por consulta (Bs)','consultation_price','number',{min:0,step:'0.01'})+input('Teléfono','phone','tel',{required:false}),'Crear médico')):'';
  return head('Directorio de médicos','Especialidades, precios de consulta y disponibilidad semanal.')+(creator?`<div class="split"><div>${creator}</div><div>${panel('Nuestro equipo',cards?`<div class="doctor-list">${cards}</div>`:empty())}</div></div>`:panel('Nuestro equipo',cards?`<div class="doctor-list">${cards}</div>`:empty()));
}
async function schedulesView(){
  const [docs,items]=await Promise.all([api('/api/doctors'),api('/api/schedules')]);
  const left=panel('Crear horario',form('schedule',select('Médico','doctor_id',opts(docs))+select('Día de la semana','weekday',days.map((d,i)=>({value:i,label:d})))+
    input('Desde','start_time','time')+input('Hasta','end_time','time'),'Guardar horario'));
  const rows=items.map(s=>`<tr><td class="name">${escapeHtml(s.doctor_name)}</td><td>${days[Number(s.weekday)]}</td><td>${String(s.start_time).slice(0,5)}</td><td>${String(s.end_time).slice(0,5)}</td></tr>`);
  return head('Horarios médicos','Configura la disponibilidad semanal de cada médico.')+`<div class="split"><div>${left}</div>${panel('Horarios actuales',table(['Médico','Día','Inicio','Fin'],rows))}</div>`;
}
async function specialtiesView(){
  const items=await api('/api/specialties');
  const left=panel('Nueva especialidad',form('specialty',input('Nombre de especialidad','name'),'Agregar'));
  const rows=items.map(s=>`<tr><td class="name">${escapeHtml(s.name)}</td><td>#${s.id}</td></tr>`);
  return head('Especialidades','Catálogo de servicios médicos disponibles.')+`<div class="split"><div>${left}</div>${panel('Especialidades registradas',table(['Nombre','ID'],rows))}</div>`;
}
async function recordsView(){
  const [records,appointments]=await Promise.all([api('/api/records'),state.user.role==='medico'?api('/api/appointments'):Promise.resolve([])]);
  const ids=new Set(records.map(r=>Number(r.appointment_id)));
  const possible=appointments.filter(a=>['confirmada','completada'].includes(a.status) && !ids.has(Number(a.id)) && new Date(String(a.scheduled_at).replace(' ','T'))<=new Date());
  const formRecord=state.user.role==='medico'?panel('Registrar atención',form('record',select('Cita atendida','appointment_id',possible.map(a=>({value:a.id,label:`#${a.id} · ${a.patient_name} · ${fmtDate(a.scheduled_at)}`})))+
    textarea('Síntomas','symptoms')+textarea('Diagnóstico','diagnosis')+textarea('Tratamiento / Indicaciones','treatment')+
    `<div class="field wide"><label for="rec-notes">Observaciones</label><textarea id="rec-notes" name="notes" placeholder="Notas adicionales (opcional)"></textarea></div>`,'Guardar historia clínica')+`<p class="sub" style="font-size:.76rem">Solo puedes registrar una consulta de tus propias citas confirmadas que ya hayan comenzado. La cita se marca como completada.</p>`):'';
  const cards=records.map(r=>`<div class="record"><strong>🩺 ${escapeHtml(r.patient_name)} · Cita #${r.appointment_id}</strong><small>${fmtDate(r.created_at)} · ${escapeHtml(r.doctor_name)}</small><p><b>Síntomas:</b> ${escapeHtml(r.symptoms)}</p><p><b>Diagnóstico:</b> ${escapeHtml(r.diagnosis)}</p><p><b>Tratamiento:</b> ${escapeHtml(r.treatment)}</p>${r.notes?`<p><b>Notas:</b> ${escapeHtml(r.notes)}</p>`:''}</div>`).join('');
  return head('Historias clínicas',state.user.role==='medico'?'Documenta síntomas, diagnósticos y tratamientos de las consultas.':'Consulta tus registros médicos generados por los profesionales.')+(formRecord?`<div class="split"><div>${formRecord}</div><div>${panel('Registros médicos',cards||empty('Aún no hay consultas registradas.'))}</div></div>`:panel('Mis registros médicos',cards||empty('Aún no hay consultas registradas.')));
}
async function invoicesView(){
  const [invoices,appointments]=await Promise.all([api('/api/invoices'),['recepcionista','administrador'].includes(state.user.role)?api('/api/appointments'):Promise.resolve([])]);
  const invoiced=new Set(invoices.map(i=>Number(i.appointment_id)));
  const possible=appointments.filter(a=>['confirmada','completada'].includes(a.status) && !invoiced.has(Number(a.id)));
  const creator=['recepcionista','administrador'].includes(state.user.role)?panel('Emitir factura',form('invoice',select('Cita','appointment_id',possible.map(a=>({value:a.id,label:`#${a.id} · ${a.patient_name} · ${fmtDate(a.scheduled_at)}`})))+
    select('Tipo de facturación','billing_type',[{value:'normal',label:'Normal — tarifa completa'},{value:'seguro',label:'Seguro — 20% descuento (demo)'}]),'Generar factura')+
    `<p class="sub" style="font-size:.76rem">Facturación académica: el descuento del 20% es un ejemplo y no constituye cobertura de seguro ni factura fiscal oficial.</p>`):'';
  const rows=invoices.map(i=>`<tr><td class="name">#${i.id}</td><td>Cita #${i.appointment_id}</td><td>${escapeHtml(i.patient_name)}</td><td>${escapeHtml(i.billing_type)}</td><td>${money(i.subtotal)}</td><td>${money(i.discount)}</td><td><b>${money(i.total)}</b></td><td>${badge(i.status)}</td><td>${['recepcionista','administrador'].includes(state.user.role)&&i.status==='emitida'?`<button class="secondary small" data-pay="${i.id}">Registrar pago</button>`:'—'}</td></tr>`);
  const saldo=invoices.filter(i=>i.status==='emitida').reduce((sum,i)=>sum+Number(i.total),0);
  const resumen=`<div class="payments-summary"><div class="summary-tile"><small>Saldo por pagar</small><b>${money(saldo)}</b><span>${saldo>0?'Tienes facturas pendientes':'Estás al día'}</span></div><div class="summary-tile"><small>Estado</small><b>${saldo>0?'Pendiente':'✓ Al día'}</b><span>Pagos registrados: ${invoices.filter(i=>i.status==='pagada').length}</span></div></div>`;
  return head('Facturación','Cálculo de consultas y seguimiento de pagos.')+resumen+(creator?`<div class="split"><div>${creator}</div><div>${panel('Facturas emitidas',table(['Factura','Cita','Paciente','Tipo','Subtotal','Descuento','Total','Estado','Acciones'],rows))}</div></div>`:panel('Mis facturas',table(['Factura','Cita','Paciente','Tipo','Subtotal','Descuento','Total','Estado','Acciones'],rows)));
}
async function usersView(){
  const users=await api('/api/users');
  const left=panel('Crear personal administrativo',form('user',input('Nombre completo','name')+input('Correo','email','email')+input('Contraseña inicial','password','password')+
    select('Rol','role',[{value:'recepcionista',label:'Recepcionista'},{value:'administrador',label:'Administrador'}])+input('Teléfono','phone','tel',{required:false}),'Crear usuario'));
  const rows=users.map(u=>`<tr><td class="name">${escapeHtml(u.name)}</td><td>${escapeHtml(u.email)}</td><td>${badge(u.role)}</td><td>${u.active?'Activo':'Inactivo'}</td></tr>`);
  return head('Usuarios y acceso','Administración de cuentas y perfiles separados.')+`<div class="split"><div>${left}</div>${panel('Usuarios registrados',table(['Nombre','Correo','Rol','Estado'],rows))}</div>`;
}
async function notificationsView(){
  const notices=await api('/api/notifications');
  const cards=notices.map(n=>`<article class="notice ${n.read_at?'':'unread'}"><span class="ico">${n.read_at?'📨':'🔔'}</span><div class="info"><b>${escapeHtml(n.title)}</b><p>${escapeHtml(n.message)}</p><time>${fmtDate(n.created_at)}</time></div>${n.read_at?'':`<button class="secondary small" data-read="${n.id}">Marcar leída</button>`}</article>`).join('');
  return head('Notificaciones','Mensajes internos sobre citas, consultas y facturas.')+`<div class="notifications">${cards||empty('Todavía no tienes notificaciones.')}</div>`;
}

app.addEventListener('click',async e=>{
  const button=e.target.closest('button');
  if(!button || button.disabled || state.busy)return;
  if(button.hasAttribute('data-show-register')){registerScreen();return;}
  if(button.hasAttribute('data-back-login')){history.replaceState({},'','/paciente');loginScreen();return;}
  if(button.hasAttribute('data-bell')){await toggleNotifications();return;}
  if(button.hasAttribute('data-role')){state.role=button.dataset.role;history.replaceState({},'',`/${state.role}`);loginScreen();return;}
  if(button.hasAttribute('data-demo')){
    document.querySelector('#login-email').value=demo[state.role][0];
    document.querySelector('#login-password').value=demo[state.role][1];
    return;
  }
  if(button.hasAttribute('data-view')){await go(button.dataset.view);return;}
  if(button.hasAttribute('data-logout')){
    state.busy=true;
    try{await api('/api/auth/logout',{method:'POST'});state.user=null;state.csrf=null;const guest=await api('/api/auth/me');state.csrf=guest.csrf;loginScreen();}
    catch(err){flash(err.message,true)}finally{state.busy=false}return;
  }
  const id=Number(button.dataset.id||button.dataset.pay||button.dataset.read);
  let url='', status='';
  if(button.hasAttribute('data-status')){
    status=button.dataset.status;
    if(status==='cancelada'&&!window.confirm('¿Deseas cancelar esta cita?'))return;
    url=`/api/appointments/${id}/status`;
  }else if(button.hasAttribute('data-pay')){if(!window.confirm('¿Registrar el pago de esta factura?'))return;url=`/api/invoices/${id}/pay`;}
  else if(button.hasAttribute('data-read')){url=`/api/notifications/${id}/read`;}
  if(!url)return;
  state.busy=true;button.disabled=true;
  try{await api(url,{method:'PATCH',body:status?{status}:{}});flash('Operación realizada correctamente.');await go(state.view);}
  catch(err){flash(err.message,true);button.disabled=false}finally{state.busy=false;}
});
app.addEventListener('submit',async e=>{
  const target=e.target.closest('form[data-form]');
  if(!target)return;
  e.preventDefault();if(state.busy)return;
  const action=target.dataset.form;
  const values=Object.fromEntries(new FormData(target).entries());
  const endpoints={register:'/api/auth/register',login:'/api/auth/login',appointment:'/api/appointments',patient:'/api/patients',doctor:'/api/doctors',schedule:'/api/schedules',specialty:'/api/specialties',record:'/api/records',invoice:'/api/invoices',user:'/api/users'};
  if(action==='login')values.role=state.role;
  if(!endpoints[action])return;
  state.busy=true;
  const button=target.querySelector('button[type="submit"]');if(button)button.disabled=true;
  try{
    const result=await api(endpoints[action],{method:'POST',body:values});
    if(action==='register'){flash('Cuenta registrada. Ahora inicia sesión como Paciente.');loginScreen();}
    else if(action==='login'){
      state.user=result.user;state.csrf=result.csrf;state.view='dashboard';
      flash('Sesión iniciada correctamente.');await go('dashboard');
    }else{flash('Registro guardado correctamente.');await go(state.view);}
  }catch(err){flash(err.message,true);if(button)button.disabled=false;}
  finally{state.busy=false;}
});
(async function start(){
  try{
    const result=await api('/api/auth/me');
    if(result.user){state.user=result.user;state.csrf=result.csrf;state.profileId=result.profile_id;await go('dashboard');}
    else{state.csrf=result.csrf;loginScreen();}
  }catch(e){loginScreen();flash(e.message,true);}
})();

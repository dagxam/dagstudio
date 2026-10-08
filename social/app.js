'use strict';
const targetUserId = 526046650;
const draftKey = 'dag-social-personal-drafts-v1';
const byId = id => document.getElementById(id);
const editor = byId('postText');
const view = byId('previewText');
const publishButton = byId('publishBtn');
let prepared = '';
let authorized = false;
let toastHandle;
function toast(message) {
  const el = byId('toast'); el.textContent = message;
  el.classList.add('show'); clearTimeout(toastHandle);
  toastHandle = setTimeout(() => el.classList.remove('show'), 4500);
}
function setIdentity(ok, description, checked = false) {
  authorized = ok;
  // VK Bridge user identification is not permission for unattended wall.post.
  const status = ok ? 'Вход подтверждён' : checked ? 'Вход не подтверждён' : 'Вход не проверен';
  for (const id of ['vkStatus', 'autopilotVkStatus']) {
    const node = byId(id);
    if (node) {
      node.textContent = status;
      node.classList.toggle('connected', ok);
    }
  }
  byId('vkHint').textContent = description;
  byId('accountStatus').textContent = description;
  updateButton();
}
function updateButton() {publishButton.disabled = !authorized || !prepared || prepared !== editor.value.trim();}
function insideVK() {return !!(window.vkBridge?.send && (window.vkBridge?.isEmbedded?.() || window.vkBridge?.isWebView?.()));}
async function checkIdentity() {
  if (!insideVK()) return setIdentity(false, 'Открой приложение из ВКонтакте. Открытие сайта в обычном браузере предназначено для предпросмотра.');
  try {
    await window.vkBridge.send('VKWebAppInit');
    const data = await window.vkBridge.send('VKWebAppGetUserInfo');
    setIdentity(Number(data?.id) === targetUserId, Number(data?.id) === targetUserId
      ? 'Вход в нужный профиль подтверждён через VK Bridge. Это не даёт прав на автоматические публикации.'
      : 'Открыт другой аккаунт или профиль не определён. Публикация заблокирована.', true);
  } catch {setIdentity(false, 'Не удалось подтвердить профиль через VK Bridge.', true);}
}
function showPage(name) {
  document.querySelectorAll('.page').forEach(p => p.classList.toggle('hidden', p.id !== 'page-' + name));
  document.querySelectorAll('.nav').forEach(b => b.classList.toggle('active', b.dataset.page === name));
  if (name === 'drafts') renderDrafts();
}
function preview() {
  const value = editor.value.trim();
  if (!value) return toast('Введите текст публикации.');
  prepared = value; view.textContent = value; view.classList.remove('empty');
  byId('copyBtn').disabled = false; updateButton();
  toast('Предпросмотр готов. Ничего не опубликовано.');
}
function loadDrafts() {
  try {const a = JSON.parse(localStorage.getItem(draftKey) || '[]');
    return Array.isArray(a) ? a.filter(x => typeof x?.id === 'string' && typeof x?.text === 'string').slice(0,30) : [];
  } catch {return [];}
}
function saveDrafts(items) {
  try {localStorage.setItem(draftKey, JSON.stringify(items.slice(0,30))); byId('draftCount').textContent = String(loadDrafts().length); return true;}
  catch {toast('Локальное хранилище браузера недоступно.'); return false;}
}
function saveDraft() {
  const value = editor.value.trim(); if (!value) return toast('Сначала введи текст.');
  const data = loadDrafts(); data.unshift({id:crypto.randomUUID(),text:value,date:new Date().toISOString()});
  if (saveDrafts(data)) toast('Черновик сохранён в браузере.');
}
function renderDrafts() {
  const root = byId('draftItems'); root.replaceChildren();
  const items = loadDrafts(); byId('emptyDrafts').hidden = items.length > 0;
  for (const item of items) {
    const article = document.createElement('article'); article.className = 'draft';
    const date = document.createElement('time'); date.textContent = new Date(item.date).toLocaleString('ru-RU');
    const content = document.createElement('p'); content.textContent = item.text.slice(0,700);
    const actions = document.createElement('div'); actions.className = 'actions';
    const open = document.createElement('button'); open.className = 'btn accent'; open.textContent = 'Редактировать';
    open.onclick = () => {editor.value = item.text; editor.dispatchEvent(new Event('input')); prepared = ''; view.textContent = 'Нажми «Предпросмотр».'; view.classList.add('empty'); byId('copyBtn').disabled = true; updateButton(); showPage('compose');};
    const remove = document.createElement('button'); remove.className = 'btn secondary'; remove.textContent = 'Удалить';
    remove.onclick = () => {if (confirm('Удалить этот черновик?')) {saveDrafts(loadDrafts().filter(d=>d.id!==item.id)); renderDrafts();}};
    actions.append(open,remove); article.append(date,content,actions); root.append(article);
  }
}
async function publish() {
  if (!insideVK() || !authorized || !prepared || prepared !== editor.value.trim()) return;
  if (!confirm('Открыть официальный диалог публикации на личной стене VK?')) return;
  publishButton.disabled = true;
  try {
    const result = await window.vkBridge.send('VKWebAppShowWallPostBox',{message:prepared});
    toast(result?.post_id ? 'ВК подтвердил публикацию записи №' + result.post_id : 'Диалог завершён. Проверь появление записи в профиле.');
  } catch (e) {toast('Публикация отменена или отклонена ВК: ' + String(e?.error_data?.error_reason || e?.message || 'неизвестная ошибка'));}
  finally {updateButton();}
}
document.querySelectorAll('.nav').forEach(b => b.onclick = () => showPage(b.dataset.page));
editor.addEventListener('input', () => {byId('charCounter').textContent = editor.value.length.toLocaleString('ru-RU') + ' знаков'; updateButton();});
byId('previewBtn').onclick = preview;
byId('saveDraftBtn').onclick = saveDraft;
byId('publishBtn').onclick = publish;
byId('checkVK').onclick = checkIdentity;
byId('autopilotCheckVK').onclick = () => {showPage('accounts'); checkIdentity();};
byId('copyBtn').onclick = async () => {try {await navigator.clipboard.writeText(prepared);toast('Текст скопирован.');}catch {toast('Копирование недоступно в этом браузере.');}};
byId('draftCount').textContent = String(loadDrafts().length);
setIdentity(false,'Проверяем VK Bridge…');
window.addEventListener('load',checkIdentity);

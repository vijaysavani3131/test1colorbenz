import React, { useEffect, useMemo, useState } from 'react';
import { api } from './lib/api';

const STATUS_OPTIONS = ['intake','needs_info','ready_for_review','in_progress','waiting_customer','waiting_external','resolved','closed'];
const PRIORITIES = ['low','normal','high','urgent'];

const money = (paise = 0) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format((paise || 0) / 100);
const when = (value) => value ? new Date(value).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const title = (value = '') => value.replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase());

function Login({ onLoggedIn }) {
  const [form, setForm] = useState({ email: '', password: '' });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setError('');
    try {
      const result = await api.adminLogin(form.email, form.password);
      localStorage.setItem('bkw_admin_token', result.token);
      onLoggedIn(result.user);
    } catch (err) { setError(err.message); }
    finally { setBusy(false); }
  };

  return <div className="admin-login-page">
    <div className="admin-login-card">
      <div className="admin-login-brand"><span className="brand-mark">B</span><div><strong>BasKarwaDo</strong><small>Operations Console</small></div></div>
      <div className="admin-login-copy"><span className="section-eyebrow">SECURE STAFF ACCESS</span><h1>Resolve cases, not spreadsheets.</h1><p>One queue for web + WhatsApp intake, documents, payments, AI summaries and human action.</p></div>
      <form onSubmit={submit} className="admin-login-form">
        <label>Email<input type="email" required autoComplete="email" value={form.email} onChange={(e)=>setForm({...form,email:e.target.value})} placeholder="ops@baskrwado.in"/></label>
        <label>Password<input type="password" required autoComplete="current-password" value={form.password} onChange={(e)=>setForm({...form,password:e.target.value})} placeholder="••••••••••"/></label>
        {error && <div className="error-box">{error}</div>}
        <button className="btn btn-dark admin-login-button" disabled={busy}>{busy ? 'Signing in…' : 'Sign in securely →'}</button>
      </form>
      <p className="security-foot">Bearer-token session · 30-day expiry · passwords are never stored in the browser.</p>
    </div>
  </div>;
}

function Metric({ label, value, accent }) {
  return <div className={`ops-metric ${accent || ''}`}><span>{label}</span><strong>{value}</strong></div>;
}

function CaseRow({ item, selected, onClick }) {
  return <button className={`ops-case-row ${selected ? 'selected' : ''}`} onClick={onClick}>
    <div className="ops-case-main"><div><strong>{item.public_id}</strong><span className={`priority-dot priority-${item.priority}`}/></div><small>{item.name} · {item.phone}</small></div>
    <div className="ops-case-service">{item.service?.title || title(item.service_slug)}<small>{item.source} · {item.locale?.toUpperCase()}</small></div>
    <div><span className={`status-chip status-${item.status}`}>{item.status_label || title(item.status)}</span></div>
    <div className="readiness-mini"><span style={{width:`${item.readiness_score || 0}%`}}/><small>{item.readiness_score || 0}%</small></div>
  </button>;
}

function Detail({ data, staff, onRefresh }) {
  const [saving, setSaving] = useState(false);
  const [note, setNote] = useState('');
  const [customerVisible, setCustomerVisible] = useState(false);
  const [error, setError] = useState('');
  const [tab, setTab] = useState('overview');
  const [edit, setEdit] = useState({
    status: data.status,
    priority: data.priority,
    assigned_admin_user_id: data.assignee?.id || '',
    fee_rupees: ((data.fee_paise || 0) / 100).toString(),
  });

  useEffect(() => {
    setEdit({ status:data.status, priority:data.priority, assigned_admin_user_id:data.assignee?.id||'', fee_rupees:((data.fee_paise||0)/100).toString() });
  }, [data.public_id, data.status, data.priority, data.assignee?.id, data.fee_paise]);

  const save = async () => {
    setSaving(true); setError('');
    try {
      await api.adminUpdateCase(data.public_id, {
        status: edit.status,
        priority: edit.priority,
        assigned_admin_user_id: edit.assigned_admin_user_id ? Number(edit.assigned_admin_user_id) : null,
        fee_paise: Math.max(0, Math.round(Number(edit.fee_rupees || 0) * 100)),
      });
      await onRefresh();
    } catch (e) { setError(e.message); }
    finally { setSaving(false); }
  };

  const addNote = async (e) => {
    e.preventDefault(); if (!note.trim()) return;
    setSaving(true); setError('');
    try { await api.adminAddNote(data.public_id, note, customerVisible); setNote(''); setCustomerVisible(false); await onRefresh(); }
    catch (err) { setError(err.message); }
    finally { setSaving(false); }
  };

  const verifyDoc = async (doc, status) => {
    try { await api.adminVerifyDocument(doc.id, status); await onRefresh(); }
    catch (err) { setError(err.message); }
  };

  return <section className="ops-detail">
    <div className="ops-detail-head">
      <div><span className="section-eyebrow">CASE WORKSPACE</span><h2>{data.public_id}</h2><p>{data.name} · {data.phone} {data.email ? `· ${data.email}` : ''}</p></div>
      <div className="ops-head-badges"><span className={`status-chip status-${data.status}`}>{data.status_label}</span><span className={`priority-badge priority-${data.priority}`}>{data.priority}</span></div>
    </div>

    <div className="ops-tabs">
      {['overview','documents','timeline'].map((name)=><button key={name} className={tab===name?'active':''} onClick={()=>setTab(name)}>{title(name)}{name==='documents'&&<em>{data.documents?.length||0}</em>}</button>)}
    </div>

    {error && <div className="error-box ops-error">{error}</div>}

    {tab === 'overview' && <div className="ops-detail-body">
      <div className="ops-two-col">
        <div className="ops-card">
          <div className="ops-card-head"><h3>Workflow control</h3><span>Human-owned</span></div>
          <div className="ops-form-grid">
            <label>Status<select value={edit.status} onChange={(e)=>setEdit({...edit,status:e.target.value})}>{STATUS_OPTIONS.map(v=><option key={v} value={v}>{title(v)}</option>)}</select></label>
            <label>Priority<select value={edit.priority} onChange={(e)=>setEdit({...edit,priority:e.target.value})}>{PRIORITIES.map(v=><option key={v} value={v}>{title(v)}</option>)}</select></label>
            <label>Assignee<select value={edit.assigned_admin_user_id} onChange={(e)=>setEdit({...edit,assigned_admin_user_id:e.target.value})}><option value="">Unassigned</option>{staff.filter(s=>s.active).map(s=><option key={s.id} value={s.id}>{s.name} · {s.role}</option>)}</select></label>
            <label>Service fee (₹)<input type="number" min="0" value={edit.fee_rupees} onChange={(e)=>setEdit({...edit,fee_rupees:e.target.value})}/></label>
          </div>
          <button className="btn btn-dark" disabled={saving} onClick={save}>{saving?'Saving…':'Save workflow changes'}</button>
        </div>

        <div className="ops-card case-health-card">
          <div className="ops-card-head"><h3>Case health</h3><span>{data.service?.title}</span></div>
          <div className="health-ring" style={{'--score':`${data.readiness_score||0}%`}}><strong>{data.readiness_score||0}%</strong><span>readiness</span></div>
          <div className="health-meta"><span><small>Payment</small><strong>{title(data.payment_status)}</strong></span><span><small>Fee</small><strong>{money(data.fee_paise)}</strong></span><span><small>Source</small><strong>{title(data.source)}</strong></span></div>
        </div>
      </div>

      <div className="ops-card">
        <div className="ops-card-head"><h3>AI intake report</h3><span className={`risk-pill risk-${data.ai_report?.risk_level || data.metadata?.risk_tier || 'amber'}`}>{data.ai_report?.risk_level || data.metadata?.risk_tier || 'pending'} risk</span></div>
        {data.ai_report ? <div className="ai-report-grid">
          <div className="ai-summary"><small>SUMMARY</small><p>{data.ai_report.summary}</p></div>
          <div><small>NEXT STEP</small><p>{data.ai_report.recommended_next_step}</p></div>
          <div><small>CUSTOMER NEED</small><p>{data.ai_report.customer_need}</p></div>
          <div><small>WARNINGS</small>{data.ai_report.warnings?.length ? <ul>{data.ai_report.warnings.map((w,i)=><li key={i}>{w}</li>)}</ul> : <p>No AI warnings. Human review rules still apply.</p>}</div>
        </div> : <div className="empty-panel">AI report will appear after intake/document processing. Case handling does not depend on AI availability.</div>}
      </div>

      <div className="ops-two-col">
        <div className="ops-card">
          <div className="ops-card-head"><h3>Structured intake</h3><span>{Object.keys(data.answers||{}).length} fields</span></div>
          <dl className="answer-list">{Object.entries(data.answers||{}).map(([k,v])=><div key={k}><dt>{title(k)}</dt><dd>{v || '—'}</dd></div>)}</dl>
        </div>
        <div className="ops-card">
          <div className="ops-card-head"><h3>Case notes</h3><span>{data.notes?.length||0}</span></div>
          <form className="note-form" onSubmit={addNote}><textarea rows="4" value={note} onChange={(e)=>setNote(e.target.value)} placeholder="Add internal context or a customer-visible update…"/><label className="switch-line"><input type="checkbox" checked={customerVisible} onChange={(e)=>setCustomerVisible(e.target.checked)}/> Visible to customer on tracking page</label><button className="btn btn-dark btn-small" disabled={saving}>Add note</button></form>
          <div className="note-list">{(data.notes||[]).map(n=><article key={n.id}><div><strong>{n.author||'Staff'}</strong><small>{when(n.created_at)}</small></div><p>{n.note}</p>{n.customer_visible&&<span>Customer visible</span>}</article>)}</div>
        </div>
      </div>
    </div>}

    {tab === 'documents' && <div className="ops-detail-body">
      <div className="ops-card">
        <div className="ops-card-head"><h3>Private document vault</h3><span>Authenticated access only</span></div>
        <div className="document-grid">{(data.documents||[]).map(doc=><article className="document-card" key={doc.id}><div className="document-icon">{doc.mime_type?.includes('pdf')?'PDF':'FILE'}</div><div className="document-info"><strong>{doc.name}</strong><small>{doc.category} · {(doc.size_bytes/1024).toFixed(0)} KB</small><span className={`doc-status doc-${doc.status}`}>{title(doc.status)}</span></div><div className="document-actions"><button onClick={()=>api.adminDownloadDocument(doc.id,doc.name)}>Download</button><button onClick={()=>verifyDoc(doc,'verified')}>Verify</button><button onClick={()=>verifyDoc(doc,'rejected')}>Reject</button></div>{doc.extracted_data?.summary&&<p className="document-summary">AI extract: {doc.extracted_data.summary}</p>}</article>)}{!data.documents?.length&&<div className="empty-panel">No customer documents uploaded yet.</div>}</div>
      </div>
    </div>}

    {tab === 'timeline' && <div className="ops-detail-body"><div className="ops-card"><div className="ops-card-head"><h3>Audit timeline</h3><span>Append-only operational history</span></div><div className="timeline">{(data.events||[]).map(event=><article key={event.id}><span className="timeline-dot"/><div><strong>{title(event.type)}</strong><p>{event.message}</p><small>{event.actor_type} · {when(event.created_at)}</small></div></article>)}{!data.events?.length&&<div className="empty-panel">No timeline events.</div>}</div></div></div>}
  </section>;
}

export default function AdminPortal() {
  const [user, setUser] = useState(null);
  const [cases, setCases] = useState([]);
  const [metrics, setMetrics] = useState({});
  const [staff, setStaff] = useState([]);
  const [selectedId, setSelectedId] = useState('');
  const [detail, setDetail] = useState(null);
  const [filters, setFilters] = useState({ search:'', status:'', priority:'' });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const hasToken = !!localStorage.getItem('bkw_admin_token');

  const boot = async () => {
    setLoading(true); setError('');
    try {
      const [me, staffResult] = await Promise.all([api.adminMe(), api.adminStaff()]);
      setUser(me.user); setStaff(staffResult.data || []);
      await loadCases();
    } catch (err) { localStorage.removeItem('bkw_admin_token'); setUser(null); setError(err.message); }
    finally { setLoading(false); }
  };

  const loadCases = async (nextFilters = filters) => {
    const result = await api.adminCases(nextFilters);
    setCases(result.data || []); setMetrics(result.metrics || {});
    if (!selectedId && result.data?.[0]) setSelectedId(result.data[0].public_id);
  };

  const loadDetail = async (id = selectedId) => {
    if (!id) return;
    const result = await api.adminCase(id); setDetail(result.data);
  };

  useEffect(() => { if (hasToken) boot(); else setLoading(false); }, []);
  useEffect(() => { if (user && selectedId) loadDetail(selectedId).catch(e=>setError(e.message)); }, [selectedId, user]);

  const selected = useMemo(()=>cases.find(c=>c.public_id===selectedId),[cases,selectedId]);
  const applyFilters = async (e) => { e?.preventDefault(); try{await loadCases(filters);}catch(err){setError(err.message);} };
  const refresh = async () => { await Promise.all([loadCases(), loadDetail()]); };
  const logout = async () => { try{await api.adminLogout();}catch{} localStorage.removeItem('bkw_admin_token'); setUser(null); };

  if (!user) return <Login onLoggedIn={(u)=>{setUser(u); setLoading(false); setTimeout(()=>boot(),0);}}/>;
  if (loading) return <div className="admin-loading">Loading operations console…</div>;

  return <div className="ops-shell">
    <aside className="ops-sidebar">
      <div className="ops-brand"><span className="brand-mark">B</span><div><strong>BasKarwaDo</strong><small>Operations</small></div></div>
      <nav><button className="active">◫ <span>Cases</span></button><button onClick={()=>window.open('/','_blank')}>↗ <span>Customer site</span></button></nav>
      <div className="ops-user"><div className="ops-avatar">{user.name?.[0]?.toUpperCase()}</div><div><strong>{user.name}</strong><small>{user.role}</small></div><button onClick={logout} title="Logout">↪</button></div>
    </aside>

    <main className="ops-main">
      <header className="ops-topbar"><div><span className="section-eyebrow">LIVE OPERATIONS</span><h1>Case command centre</h1></div><div className="ops-top-actions"><span className="live-indicator"><i/> Queue connected</span><button className="btn btn-light btn-small" onClick={refresh}>↻ Refresh</button></div></header>
      {error&&<div className="error-box ops-global-error">{error}</div>}
      <section className="ops-metrics"><Metric label="Open cases" value={metrics.open||0}/><Metric label="Ready for review" value={metrics.ready_for_review||0} accent="metric-mint"/><Metric label="Waiting customer" value={metrics.waiting_customer||0}/><Metric label="Urgent" value={metrics.urgent||0} accent="metric-red"/></section>

      <section className="ops-workspace">
        <div className="ops-queue">
          <form className="ops-filters" onSubmit={applyFilters}><input value={filters.search} onChange={(e)=>setFilters({...filters,search:e.target.value})} placeholder="Search case, name, phone…"/><select value={filters.status} onChange={(e)=>setFilters({...filters,status:e.target.value})}><option value="">All statuses</option>{STATUS_OPTIONS.map(v=><option key={v} value={v}>{title(v)}</option>)}</select><select value={filters.priority} onChange={(e)=>setFilters({...filters,priority:e.target.value})}><option value="">All priorities</option>{PRIORITIES.map(v=><option key={v} value={v}>{title(v)}</option>)}</select><button className="btn btn-dark btn-small">Filter</button></form>
          <div className="ops-queue-head"><span>{cases.length} loaded</span><small>Sorted by latest activity</small></div>
          <div className="ops-case-list">{cases.map(item=><CaseRow key={item.public_id} item={item} selected={item.public_id===selectedId} onClick={()=>setSelectedId(item.public_id)}/>)}{!cases.length&&<div className="empty-panel">No cases match this filter.</div>}</div>
        </div>
        <div className="ops-detail-wrap">{detail && selected ? <Detail key={detail.public_id} data={detail} staff={staff} onRefresh={refresh}/> : <div className="empty-detail"><strong>Select a case</strong><p>Case details, evidence, AI report and timeline will appear here.</p></div>}</div>
      </section>
    </main>
  </div>;
}

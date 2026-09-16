import React, { useEffect, useMemo, useState } from 'react';
import { Link, Route, Routes, useNavigate } from 'react-router-dom';
import { api } from './lib/api';

const fallbackServices = [
  { slug: 'money_recovery', title: 'Paisa Wapas', short: 'Refund, failed service, cancelled booking or money stuck.', icon: '₹', tone: 'mint' },
  { slug: 'after_sales', title: 'After-Sales & Warranty', short: 'Repair, warranty, replacement and service follow-up.', icon: '↻', tone: 'blue' },
  { slug: 'identity_repair', title: 'Identity Repair', short: 'PAN, Aadhaar, bank or document name / DOB mismatch.', icon: 'ID', tone: 'violet' },
  { slug: 'move_life', title: 'Move My Life', short: 'Address updates after shifting home or city.', icon: '⌂', tone: 'amber' },
  { slug: 'marriage_sync', title: 'Marriage Sync', short: 'Name, address, nominee and record updates after marriage.', icon: '∞', tone: 'rose' },
  { slug: 'new_baby', title: 'New Baby Setup', short: 'A structured newborn document and benefit checklist.', icon: '+', tone: 'cyan' },
  { slug: 'after_loss', title: 'After-Loss Admin', short: 'Organise bank, insurance, account and family administration.', icon: '◌', tone: 'slate' },
];

const copy = {
  en: {
    navServices: 'Services', navHow: 'How it works', navTrack: 'Track case', navAdmin: 'Admin',
    heroBadge: 'India-first life admin & recovery',
    heroTitleA: 'Kaam atka hai?', heroTitleB: 'Bas batao.',
    heroText: 'Refund ho, warranty issue ho, documents mismatch ho ya life-event paperwork — WhatsApp karo ya online case start karo. Process hum organise karenge.',
    whatsapp: 'Start on WhatsApp', online: 'Start online',
    trust1: 'Hindi · English · Gujarati', trust2: 'Human-reviewed cases', trust3: 'No passwords or PINs',
    servicesEyebrow: 'ONE BACKEND, MANY ENTRY DOORS', servicesTitle: 'Aapki problem se start hota hai — software se nahi.',
    servicesText: 'Aapko process samajhne ki zarurat nahi. Sirf batayein kya atka hai; system required details collect karke case-ready report banata hai.',
    howEyebrow: 'HOW IT WORKS', howTitle: '3 steps. No bureaucracy lecture.',
    how1: 'Problem batao', how1t: 'WhatsApp ya web par apni situation natural language mein explain karo.',
    how2: 'Details collect hoti hain', how2t: 'System sirf relevant questions poochta hai aur documents / screenshots ko case se attach karta hai.',
    how3: 'Human process aage badhata hai', how3t: 'Admin team report review karke correct next action, escalation ya assisted workflow handle karti hai.',
    promise: 'BasKarwaDo promise', promiseText: 'AI repetitive intake ko fast karega. Sensitive, legal, financial ya high-risk decisions human review ke bina execute nahi honge.',
    startTitle: 'Online case start karein', startText: 'WhatsApp convenient nahi? Same backend ke through yahin se case bana sakte hain.',
  },
  hi: {
    navServices: 'सेवाएँ', navHow: 'कैसे काम करता है', navTrack: 'केस ट्रैक करें', navAdmin: 'एडमिन',
    heroBadge: 'भारत के लिए लाइफ-एडमिन और रिकवरी',
    heroTitleA: 'काम अटका है?', heroTitleB: 'बस बताओ।',
    heroText: 'रिफंड, वारंटी, डॉक्यूमेंट मिसमैच या लाइफ-इवेंट पेपरवर्क — WhatsApp करें या ऑनलाइन केस शुरू करें। आगे की प्रक्रिया हम व्यवस्थित करेंगे।',
    whatsapp: 'WhatsApp पर शुरू करें', online: 'ऑनलाइन शुरू करें',
    trust1: 'हिंदी · English · ગુજરાતી', trust2: 'हर संवेदनशील केस पर मानव समीक्षा', trust3: 'पासवर्ड या PIN कभी नहीं',
    servicesEyebrow: 'एक बैकएंड, कई एंट्री पॉइंट', servicesTitle: 'शुरुआत आपकी समस्या से होती है — सॉफ्टवेयर से नहीं।',
    servicesText: 'आपको प्रक्रिया समझने की जरूरत नहीं। बस बताइए क्या अटका है; सिस्टम जरूरी जानकारी लेकर केस-रेडी रिपोर्ट बनाएगा।',
    howEyebrow: 'कैसे काम करता है', howTitle: '3 स्टेप। बिना बेकार भाग-दौड़ के।',
    how1: 'समस्या बताइए', how1t: 'WhatsApp या वेबसाइट पर अपनी स्थिति सामान्य भाषा में बताइए।',
    how2: 'जरूरी जानकारी इकट्ठी होगी', how2t: 'सिस्टम केवल संबंधित सवाल पूछेगा और जरूरी दस्तावेज केस से जोड़ेगा।',
    how3: 'मानव टीम आगे बढ़ाएगी', how3t: 'एडमिन टीम रिपोर्ट देखकर सही अगला कदम या escalation संभालेगी।',
    promise: 'BasKarwaDo वादा', promiseText: 'AI intake तेज करेगा; संवेदनशील, कानूनी या वित्तीय निर्णय मानव समीक्षा के बिना execute नहीं होंगे।',
    startTitle: 'ऑनलाइन केस शुरू करें', startText: 'WhatsApp उपयोग नहीं करना? इसी सिस्टम पर ऑनलाइन केस बना सकते हैं।',
  },
  gu: {
    navServices: 'સેવાઓ', navHow: 'કેવી રીતે કામ કરે', navTrack: 'કેસ ટ્રેક કરો', navAdmin: 'એડમિન',
    heroBadge: 'ભારત માટે life-admin અને recovery',
    heroTitleA: 'કામ અટક્યું છે?', heroTitleB: 'બસ કહો.',
    heroText: 'Refund હોય, warranty issue હોય, document mismatch હોય કે life-event paperwork — WhatsApp કરો અથવા online case શરૂ કરો. આગળની process અમે organise કરીશું.',
    whatsapp: 'WhatsApp પર શરૂ કરો', online: 'Online શરૂ કરો',
    trust1: 'ગુજરાતી · हिंदी · English', trust2: 'Sensitive cases human-reviewed', trust3: 'Password કે PIN ક્યારેય નહીં',
    servicesEyebrow: 'એક backend, ઘણા entry doors', servicesTitle: 'શરૂઆત તમારી problemથી — softwareથી નહીં.',
    servicesText: 'Process સમજવાની જરૂર નથી. શું અટક્યું છે એટલું કહો; system જરૂરી details લઇ case-ready report બનાવશે.',
    howEyebrow: 'કેવી રીતે કામ કરે', howTitle: '3 steps. ફાલતુ દોડધામ વગર.',
    how1: 'Problem કહો', how1t: 'WhatsApp અથવા website પર તમારી situation સામાન્ય ભાષામાં કહો.',
    how2: 'જરૂરી details collect થશે', how2t: 'System માત્ર relevant questions પૂછશે અને જરૂરી documents case સાથે જોડશે.',
    how3: 'Human team આગળ process કરશે', how3t: 'Admin team report review કરી યોગ્ય next action અથવા escalation સંભાળશે.',
    promise: 'BasKarwaDo promise', promiseText: 'AI intake ઝડપી કરશે; sensitive, legal કે financial action human review વગર execute નહીં થાય.',
    startTitle: 'Online case શરૂ કરો', startText: 'WhatsApp convenient નથી? Same backend પરથી અહીં case બનાવી શકો છો.',
  },
};

const questions = {
  money_recovery: [
    ['merchant', 'Company / merchant / airline ka naam?'], ['amount', 'Kitna amount atka hai?'], ['issue_date', 'Issue / refund kab se pending hai?'], ['reference', 'Order / booking / transaction ID (if available)'], ['summary', 'Short mein kya hua?'],
  ],
  after_sales: [
    ['brand', 'Brand / seller ka naam?'], ['product', 'Product aur model?'], ['purchase_date', 'Purchase date?'], ['warranty', 'Warranty active lag rahi hai?'], ['summary', 'Exact problem kya hai?'],
  ],
  identity_repair: [
    ['documents', 'Kaunse documents mismatch hain?'], ['canonical_name', 'Aapke hisaab se correct legal name / detail kya hai?'], ['blocked_action', 'Mismatch ki wajah se kya kaam atka hai?'], ['summary', 'Mismatch briefly describe karein.'],
  ],
  move_life: [
    ['from_city', 'Kahan se shift hue?'], ['to_city', 'Kahan shift hue?'], ['move_date', 'Move date?'], ['records', 'Kin records ka address update chahiye?'],
  ],
  marriage_sync: [
    ['marriage_date', 'Marriage date?'], ['name_change', 'Name change karna hai?'], ['address_change', 'Address change bhi hai?'], ['records', 'Bank / insurance / passport / nominee me se kya update chahiye?'],
  ],
  new_baby: [
    ['birth_date', 'Baby birth date?'], ['city', 'Birth city?'], ['hospital', 'Hospital / place of birth?'], ['needs', 'Abhi kis document ya benefit ki priority hai?'],
  ],
  after_loss: [
    ['relation', 'Deceased se relation?'], ['date', 'Date of death?'], ['assets', 'Known banks / insurance / PF / investments?'], ['nominee', 'Nominee details available hain?'], ['summary', 'Sabse urgent administrative concern kya hai?'],
  ],
};

function Brand() {
  return <Link className="brand" to="/"><span className="brand-mark">B</span><span>BasKarwaDo</span></Link>;
}

function Header({ locale, setLocale }) {
  const t = copy[locale];
  return (
    <header className="site-header">
      <div className="container header-inner">
        <Brand />
        <nav className="desktop-nav">
          <a href="/#services">{t.navServices}</a><a href="/#how">{t.navHow}</a><Link to="/track">{t.navTrack}</Link>
        </nav>
        <div className="header-actions">
          <select aria-label="Language" value={locale} onChange={(e) => setLocale(e.target.value)} className="language-select">
            <option value="en">EN</option><option value="hi">हिं</option><option value="gu">ગુજ</option>
          </select>
          <Link className="btn btn-dark btn-small" to="/start">Start a case</Link>
        </div>
      </div>
    </header>
  );
}

function ServiceCard({ service, onStart }) {
  return (
    <button className={`service-card tone-${service.tone || 'mint'}`} onClick={() => onStart(service.slug)}>
      <span className="service-icon">{service.icon || '→'}</span>
      <span className="service-copy"><strong>{service.title}</strong><span>{service.short || service.description}</span></span>
      <span className="service-arrow">↗</span>
    </button>
  );
}

function Home({ locale, services }) {
  const t = copy[locale];
  const nav = useNavigate();
  const start = (slug) => nav(`/start?service=${slug}`);
  return (
    <>
      <main>
        <section className="hero-section">
          <div className="hero-glow hero-glow-one"/><div className="hero-glow hero-glow-two"/>
          <div className="container hero-grid">
            <div className="hero-copy">
              <div className="eyebrow-pill"><span className="live-dot" />{t.heroBadge}</div>
              <h1>{t.heroTitleA}<br/><span>{t.heroTitleB}</span></h1>
              <p>{t.heroText}</p>
              <div className="hero-actions">
                <a className="btn btn-whatsapp" href="https://wa.me/?text=Hi%20BasKarwaDo" target="_blank" rel="noreferrer">{t.whatsapp}<span>↗</span></a>
                <Link className="btn btn-light" to="/start">{t.online}<span>→</span></Link>
              </div>
              <div className="trust-row"><span>✓ {t.trust1}</span><span>✓ {t.trust2}</span><span>✓ {t.trust3}</span></div>
            </div>
            <div className="hero-console" aria-label="Example customer flow">
              <div className="console-top"><div><span className="console-dot green"/><span className="console-dot"/><span className="console-dot"/></div><span>CASE INTAKE · LIVE</span></div>
              <div className="phone-card">
                <div className="phone-header"><span className="avatar">B</span><div><strong>BasKarwaDo</strong><small>typically replies instantly</small></div><span className="verified">✓</span></div>
                <div className="chat-body">
                  <div className="bubble incoming">Namaste 👋 Aapko kis kaam mein help chahiye?</div>
                  <div className="quick-grid"><span>💰 Paisa Wapas</span><span>🔧 Warranty</span><span>🪪 Document</span><span>🏠 Address</span></div>
                  <div className="bubble outgoing">Mera ₹4,999 refund 14 din se pending hai.</div>
                  <div className="bubble incoming">Thik hai. Order screenshot bhej dijiye. Main details extract karke case report ready karta hoon.</div>
                  <div className="report-mini"><span className="report-check">✓</span><div><strong>Case readiness</strong><small>3/4 details verified · 1 item pending</small></div><b>75%</b></div>
                </div>
              </div>
              <div className="floating-stat stat-one"><small>Human review</small><strong>Required when sensitive</strong></div>
              <div className="floating-stat stat-two"><small>Languages</small><strong>HI · EN · GU</strong></div>
            </div>
          </div>
        </section>

        <section id="services" className="section services-section">
          <div className="container">
            <div className="section-heading"><div><span className="section-eyebrow">{t.servicesEyebrow}</span><h2>{t.servicesTitle}</h2></div><p>{t.servicesText}</p></div>
            <div className="services-grid">{services.map((s) => <ServiceCard key={s.slug} service={s} onStart={start}/>)}</div>
          </div>
        </section>

        <section id="how" className="section how-section">
          <div className="container">
            <span className="section-eyebrow">{t.howEyebrow}</span><h2 className="how-title">{t.howTitle}</h2>
            <div className="steps-grid">
              {[[1,t.how1,t.how1t],[2,t.how2,t.how2t],[3,t.how3,t.how3t]].map(([n,h,p]) => <div className="step-card" key={n}><span className="step-number">0{n}</span><h3>{h}</h3><p>{p}</p></div>)}
            </div>
            <div className="promise-card"><div className="promise-icon">◎</div><div><strong>{t.promise}</strong><p>{t.promiseText}</p></div></div>
          </div>
        </section>

        <section className="section conversion-section">
          <div className="container conversion-card"><div><span className="section-eyebrow">WEB + WHATSAPP, SAME CASE ENGINE</span><h2>{t.startTitle}</h2><p>{t.startText}</p></div><Link className="btn btn-accent" to="/start">Start your case <span>→</span></Link></div>
        </section>
      </main>
      <Footer />
    </>
  );
}

function StartCase({ services, locale }) {
  const query = new URLSearchParams(window.location.search);
  const initial = query.get('service') || '';
  const [service, setService] = useState(initial);
  const [step, setStep] = useState(initial ? 2 : 1);
  const [details, setDetails] = useState({ name: '', phone: '', email: '', locale });
  const [answers, setAnswers] = useState({});
  const [created, setCreated] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const selected = services.find((s) => s.slug === service);
  const q = questions[service] || [];

  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setError('');
    try {
      const response = await api.createCase({ ...details, service_slug: service, source: 'web' });
      if (q.length) await api.addAnswers(response.data.public_id, answers);
      setCreated(response.data); setStep(4);
    } catch (err) { setError(err.message); }
    finally { setBusy(false); }
  };

  if (created) return <PageShell title="Case created"><div className="success-card"><span className="success-mark">✓</span><h2>Aapka case ready hai.</h2><p>Case ID save kar lijiye. Isi ID aur phone number se status track hoga.</p><div className="case-id">{created.public_id}</div><div className="report-preview"><span>Case status</span><strong>{created.status_label || 'Intake complete'}</strong><span>Readiness</span><strong>{created.readiness_score ?? 0}%</strong></div><Link className="btn btn-dark" to="/track">Track this case</Link></div></PageShell>;

  return <PageShell title="Start a case" subtitle="2–5 minute structured intake. Sensitive cases are human reviewed.">
    <div className="wizard-progress"><span className={step >= 1 ? 'active' : ''}>1</span><i/><span className={step >= 2 ? 'active' : ''}>2</span><i/><span className={step >= 3 ? 'active' : ''}>3</span></div>
    {step === 1 && <div className="wizard-panel"><h2>What is stuck?</h2><p className="muted">Select the problem, not the department.</p><div className="wizard-services">{services.map((s) => <ServiceCard key={s.slug} service={s} onStart={(slug) => { setService(slug); setStep(2); }}/>)}</div></div>}
    {step === 2 && <form className="wizard-panel form-grid" onSubmit={(e) => {e.preventDefault(); setStep(3);}}><div className="form-head"><span className="service-tag">{selected?.title}</span><h2>Your contact details</h2><p>Case updates isi number par jayengi. Password/PIN kabhi mat bhejna.</p></div><label>Full name<input required value={details.name} onChange={(e)=>setDetails({...details,name:e.target.value})} placeholder="Rahul Patel"/></label><label>WhatsApp / mobile number<input required value={details.phone} onChange={(e)=>setDetails({...details,phone:e.target.value})} placeholder="+91 98xxxxxx"/></label><label>Email (optional)<input type="email" value={details.email} onChange={(e)=>setDetails({...details,email:e.target.value})} placeholder="you@example.com"/></label><label>Preferred language<select value={details.locale} onChange={(e)=>setDetails({...details,locale:e.target.value})}><option value="en">English</option><option value="hi">Hindi</option><option value="gu">Gujarati</option></select></label><div className="form-actions"><button type="button" className="btn btn-light" onClick={()=>setStep(1)}>Back</button><button className="btn btn-dark">Continue →</button></div></form>}
    {step === 3 && <form className="wizard-panel form-grid" onSubmit={submit}><div className="form-head"><span className="service-tag">{selected?.title}</span><h2>Case details</h2><p>Short answers enough hain. Team baad mein missing evidence maang sakti hai.</p></div>{q.map(([key,label]) => <label key={key}>{label}{key === 'summary' ? <textarea required value={answers[key]||''} onChange={(e)=>setAnswers({...answers,[key]:e.target.value})} rows="4" placeholder="Briefly explain..."/> : <input required={['amount','summary'].includes(key)} value={answers[key]||''} onChange={(e)=>setAnswers({...answers,[key]:e.target.value})}/>}</label>)}<label className="consent-check"><input type="checkbox" required/> <span>I confirm the information is mine / I am authorised to share it for case assistance. I will not share passwords, PINs or banking credentials.</span></label>{error && <div className="error-box">{error}</div>}<div className="form-actions"><button type="button" className="btn btn-light" onClick={()=>setStep(2)}>Back</button><button className="btn btn-dark" disabled={busy}>{busy ? 'Creating case…' : 'Create case →'}</button></div></form>}
  </PageShell>;
}

function TrackCase() {
  const [form, setForm] = useState({ publicId:'', phone:'' });
  const [data, setData] = useState(null); const [error,setError] = useState(''); const [busy,setBusy]=useState(false);
  const submit = async (e) => {e.preventDefault(); setBusy(true); setError(''); try{const r=await api.trackCase(form.publicId,form.phone);setData(r.data);}catch(err){setError(err.message);}finally{setBusy(false);}};
  return <PageShell title="Track your case" subtitle="Case ID + mobile number. No login needed for MVP."><form className="track-card" onSubmit={submit}><label>Case ID<input required value={form.publicId} onChange={(e)=>setForm({...form,publicId:e.target.value.toUpperCase()})} placeholder="BKW-ABC123"/></label><label>Mobile number<input required value={form.phone} onChange={(e)=>setForm({...form,phone:e.target.value})} placeholder="+91..."/></label><button className="btn btn-dark" disabled={busy}>{busy?'Checking…':'Check status'}</button>{error&&<div className="error-box">{error}</div>}</form>{data&&<div className="status-card"><div><span className="status-pill">{data.status_label || data.status}</span><h2>{data.service?.title || data.service_slug}</h2><p>{data.summary || 'Your case is in the BasKarwaDo workflow.'}</p></div><div className="status-stats"><span><small>Case ID</small><strong>{data.public_id}</strong></span><span><small>Readiness</small><strong>{data.readiness_score}%</strong></span><span><small>Last updated</small><strong>{data.updated_at_human || 'Recently'}</strong></span></div></div>}</PageShell>;
}

function Admin() {
  const [key,setKey]=useState(localStorage.getItem('bkw_admin_key')||''); const [cases,setCases]=useState([]); const [error,setError]=useState('');
  const load=async()=>{localStorage.setItem('bkw_admin_key',key);setError('');try{const r=await api.adminCases();setCases(r.data||[]);}catch(e){setError(e.message);}};
  return <PageShell title="Operations dashboard" subtitle="MVP internal queue — replace header-key auth with proper staff SSO before production."><div className="admin-auth"><input value={key} onChange={(e)=>setKey(e.target.value)} placeholder="Admin API key"/><button className="btn btn-dark" onClick={load}>Open queue</button></div>{error&&<div className="error-box">{error}</div>}<div className="admin-grid"><div className="metric-card"><small>Cases loaded</small><strong>{cases.length}</strong></div><div className="metric-card"><small>Need review</small><strong>{cases.filter(c=>['ready_for_review','needs_info'].includes(c.status)).length}</strong></div><div className="metric-card"><small>In progress</small><strong>{cases.filter(c=>c.status==='in_progress').length}</strong></div></div><div className="case-table-wrap"><table className="case-table"><thead><tr><th>Case</th><th>Customer</th><th>Service</th><th>Status</th><th>Readiness</th></tr></thead><tbody>{cases.map(c=><tr key={c.public_id}><td><strong>{c.public_id}</strong><small>{c.created_at_human}</small></td><td>{c.name}<small>{c.phone}</small></td><td>{c.service?.title||c.service_slug}</td><td><span className="status-pill">{c.status_label||c.status}</span></td><td>{c.readiness_score}%</td></tr>)}</tbody></table>{!cases.length&&<div className="empty-state">Enter the admin key and load the queue.</div>}</div></PageShell>;
}

function PageShell({ title, subtitle, children }) {return <><main className="page-main"><div className="container page-head"><Link to="/" className="back-link">← Home</Link><h1>{title}</h1>{subtitle&&<p>{subtitle}</p>}</div><div className="container">{children}</div></main><Footer/></>}
function Footer(){return <footer className="footer"><div className="container footer-inner"><Brand/><p>Outcome-oriented assistance for everyday Indian admin problems.</p><div><Link to="/track">Track case</Link><Link to="/admin">Admin</Link></div></div><div className="container footer-note">BasKarwaDo is an assistance platform. Official filings, regulated advice, legal representation and customer-authenticated actions remain subject to applicable rules and human review.</div></footer>}

export default function App(){
  const [locale,setLocale]=useState(localStorage.getItem('bkw_locale')||'en');
  const [services,setServices]=useState(fallbackServices);
  useEffect(()=>{localStorage.setItem('bkw_locale',locale)},[locale]);
  useEffect(()=>{api.services().then(r=>{if(r.data?.length){setServices(r.data.map((s,i)=>({...fallbackServices[i%fallbackServices.length],...s})))}}).catch(()=>{})},[]);
  return <div className="app"><Header locale={locale} setLocale={setLocale}/><Routes><Route path="/" element={<Home locale={locale} services={services}/>}/><Route path="/start" element={<StartCase services={services} locale={locale}/>}/><Route path="/track" element={<TrackCase/>}/><Route path="/admin" element={<Admin/>}/><Route path="*" element={<PageShell title="Page not found"><Link className="btn btn-dark" to="/">Go home</Link></PageShell>}/></Routes></div>;
}

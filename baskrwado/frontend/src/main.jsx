import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import './styles.css';
import './admin-fixed-sidebar.css';
import './admin-utility-bar.css';
import './admin-profile-v2.css';
import './admin-utility-bar.js';
import './admin-profile-v2.js';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>,
);

import React from 'react';
import {createRoot} from 'react-dom/client';
import App from './App';
import './style.css';
const baseFetch=window.fetch.bind(window);
window.fetch=(input:any,init:any={})=>{if(input==='/api/workspace'){input='api.php';init={...init,headers:{...init.headers,'X-CSRF-Token':(window as any).KARBAN_CSRF}};}return baseFetch(input,init)};
createRoot(document.getElementById('root')!).render(<App/>);

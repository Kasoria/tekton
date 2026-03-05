import './app.css';
import App from './App.svelte';
import { mount } from 'svelte';

const target = document.getElementById('tekton-app');
if (target) {
  mount(App, { target });
}

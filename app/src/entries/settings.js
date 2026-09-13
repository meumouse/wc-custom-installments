/**
 * settings.js — settings screen entry.
 *
 * @since 6.0.0
 */
import '../styles/main.css';
import SettingsPage from '../pages/settings/SettingsPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('wc-custom-installments-settings-app', SettingsPage);

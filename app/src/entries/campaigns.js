/**
 * campaigns.js — campaigns screen entry.
 *
 * @since 6.0.0
 */
import '../styles/main.css';
import CampaignsPage from '../pages/campaigns/CampaignsPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('wc-custom-installments-campaigns-app', CampaignsPage);

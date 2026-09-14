/**
 * addon-page.js — entry of the screens an add-on fills (Admin\Menu::get_pages()).
 *
 * The add-on's own bundle registers its renderer in the section registry this
 * app publishes; the page hands it the panel.
 *
 * @since 6.0.0
 */
import '../styles/main.css';
import AddonPage from '../pages/addon/AddonPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('wc-custom-installments-addon-page-app', AddonPage);

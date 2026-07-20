import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createI18n } from 'vue-i18n';
import './echo';
import { library } from '@fortawesome/fontawesome-svg-core';
import {
  faArrowLeft,
  faPaperPlane,
  faCheckCircle,
  faCircle,
  faUser,
  faClock,
  faSearch,
  faHome,
  faSignOut,
  faEllipsisVertical,
  faSpinner,
  faComments,
  faCircleInfo,
  faUserTie,
  faTicket,
  faUsers,
  faChartBar,
  faCog,
  faFilePdf,
  faCircleQuestion,
  faBell,
  faPaperclip,
  faBold,
  faItalic,
  faUnderline,
  faFaceSmile,
  faFileLines,
  faImage,
  faFolderOpen,
  faWandMagicSparkles,
  faBookOpen,
  faCopy,
  faLightbulb
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { messages } from './i18n/messages';

document.documentElement.dataset.theme = localStorage.getItem('helpdesk-theme') === 'dark' ? 'dark' : 'light';

library.add(
  faArrowLeft,
  faPaperPlane,
  faCheckCircle,
  faCircle,
  faUser,
  faClock,
  faSearch,
  faHome,
  faSignOut,
  faEllipsisVertical,
  faSpinner,
  faComments,
  faCircleInfo,
  faUserTie,
  faTicket,
  faUsers,
  faChartBar,
  faCog,
  faFilePdf,
  faCircleQuestion,
  faBell,
  faPaperclip,
  faBold,
  faItalic,
  faUnderline,
  faFaceSmile,
  faFileLines,
  faImage,
  faFolderOpen,
  faWandMagicSparkles,
  faBookOpen,
  faCopy,
  faLightbulb
);

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.vue');
    return pages[`./pages/${name}.vue`]();
  },
  setup({ el, App, props, plugin }) {
    const initialLocale = props.initialPage?.props?.localization?.locale ?? 'pl';
    const i18n = createI18n({
      legacy: false,
      locale: initialLocale,
      fallbackLocale: 'en',
      messages,
    });

    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(i18n)
      .component('FontAwesomeIcon', FontAwesomeIcon)
      .mount(el);
  },
});

import { Tab } from 'bootstrap';

export default function initStudentTutor() {
    const reading = document.querySelector('[data-tutor-reading]');
    if (reading) Array.from(reading.children).forEach((child, index) => {
        if (!child.matches('p, ul, ol, blockquote, table')) return;
        child.id = 'read-' + reading.dataset.tutorReading + '-' + index;
    });

    function followReading() {
        const hash = window.location.hash;
        if (!/^#read-[a-f0-9]{12}-\d+$/.test(hash)) return;
        const theory = document.getElementById('theory');
        if (!theory) return;
        const target = document.getElementById(hash.slice(1));
        const scroll = () => requestAnimationFrame(() => {
            history.replaceState(null, '', hash);
            (target || theory).scrollIntoView({block:'center'});
        });
        const tab = document.getElementById('theory-tab');
        if (tab && !tab.classList.contains('active')) {
            tab.addEventListener('shown.bs.tab', scroll, {once:true});
            Tab.getOrCreateInstance(tab).show();
        } else scroll();
    }
    followReading();
    window.addEventListener('hashchange', followReading);
    document.addEventListener('gc:notebook-rendered', followReading);
}

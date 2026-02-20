import { useState, useEffect } from '@wordpress/element';
import axios from 'axios';
import Dashboard from './Dashboard';
import Settings  from './Settings';

/**
 * Root application component.
 *
 * Reads the `socialSyncEngine` global injected by wp_localize_script and
 * configures the Axios defaults so every subsequent request automatically
 * carries the WP REST nonce header.
 */

/* ---------- tab definitions ---------- */
const TABS = [
    {
        id:    'publish',
        label: 'Publish',
        icon: (
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 }
                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
        ),
    },
    {
        id:    'settings',
        label: 'Settings',
        icon: (
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 }
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 }
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        ),
    },
];

/* ---------- App ---------- */

const App = () => {
    const { nonce, apiBase } = window.socialSyncEngine ?? {};

    // Configure Axios with the WP nonce once – all children inherit this.
    axios.defaults.headers.common['X-WP-Nonce'] = nonce;

    const [ activeTab,     setActiveTab     ] = useState( 'publish' );
    const [ status,        setStatus        ] = useState( {
        facebook: { connected: false, page_name: '' },
        linkedin: { connected: false, person_urn: '' },
    } );
    const [ loadingStatus, setLoadingStatus ] = useState( true );

    const fetchStatus = async () => {
        try {
            const { data } = await axios.get( `${ apiBase }/status` );
            setStatus( data );
        } catch ( err ) {
            console.error( 'SocialSync: could not fetch status.', err );
        } finally {
            setLoadingStatus( false );
        }
    };

    useEffect( () => {
        fetchStatus();

        // If the user was just redirected back after OAuth, re-fetch status.
        const params = new URLSearchParams( window.location.search );
        if ( params.has( 'sse_connected' ) ) {
            fetchStatus();
        }
    }, [] );

    return (
        <div className="sse-wrap min-h-screen bg-gray-50">

            {/* ── Header ── */}
            <header className="bg-white border-b border-gray-200 px-6 py-0 flex items-stretch">

                {/* Logo */}
                <div className="flex items-center gap-2.5 pr-8 py-4 border-r border-gray-100">
                    <svg className="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 }
                            d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    <span className="text-base font-semibold text-gray-900 leading-none">SocialSync Engine</span>
                </div>

                {/* Tab bar */}
                <nav className="flex items-stretch gap-1 px-4">
                    { TABS.map( ( tab ) => {
                        const active = activeTab === tab.id;
                        return (
                            <button
                                key={ tab.id }
                                onClick={ () => setActiveTab( tab.id ) }
                                className={
                                    `flex items-center gap-1.5 px-4 text-sm font-medium border-b-2 transition-colors focus:outline-none ` +
                                    ( active
                                        ? 'border-indigo-600 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' )
                                }
                            >
                                { tab.icon }
                                { tab.label }
                            </button>
                        );
                    } ) }
                </nav>
            </header>

            {/* ── Body ── */}
            <main className="p-6">
                { activeTab === 'publish' && (
                    loadingStatus ? (
                        <div className="flex items-center justify-center h-48">
                            <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600" />
                        </div>
                    ) : (
                        <Dashboard
                            initialStatus={ status }
                            onStatusChange={ fetchStatus }
                        />
                    )
                ) }

                { activeTab === 'settings' && <Settings /> }
            </main>
        </div>
    );
};

export default App;

import axios from 'axios';

/**
 * Displays the connection status for a single social platform and provides
 * Connect / Disconnect controls.
 *
 * Props:
 *   platform      string   – 'facebook' | 'linkedin'
 *   connected     bool     – whether an access token is stored
 *   label         string   – human-readable platform name
 *   icon          ReactNode
 *   accountLabel  string   – page name or person URN to display when connected
 *   onDisconnect  fn()     – called after a successful disconnect
 */
const ConnectionStatus = ( {
    platform,
    connected,
    label,
    icon,
    accountLabel = '',
    onDisconnect,
} ) => {
    const { apiBase } = window.socialSyncEngine ?? {};

    const handleConnect = () => {
        // Open the OAuth flow – the server will redirect back to this page.
        axios
            .get( `${apiBase}/oauth/${platform}/connect` )
            .then( ( { data } ) => {
                window.location.href = data.auth_url;
            } )
            .catch( () => alert( `Could not initiate ${label} connect flow.` ) );
    };

    const handleDisconnect = async () => {
        if ( ! window.confirm( `Disconnect from ${label}?` ) ) return;

        try {
            await axios.post( `${apiBase}/oauth/${platform}/disconnect` );
            onDisconnect?.();
        } catch {
            alert( `Could not disconnect from ${label}.` );
        }
    };

    return (
        <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200">
            <div className="flex items-center gap-3">
                { icon }
                <div>
                    <p className="text-sm font-medium text-gray-800">{ label }</p>
                    { connected && accountLabel && (
                        <p className="text-xs text-gray-500 truncate max-w-[180px]">{ accountLabel }</p>
                    ) }
                </div>
            </div>

            <div className="flex items-center gap-2">
                {/* Status pill */}
                <span
                    className={ `text-xs font-medium px-2 py-0.5 rounded-full ${ connected
                        ? 'bg-green-100 text-green-700'
                        : 'bg-gray-100 text-gray-500'
                    }` }
                >
                    { connected ? 'Connected' : 'Not connected' }
                </span>

                {/* Action button */}
                { connected ? (
                    <button
                        onClick={ handleDisconnect }
                        className="text-xs text-red-600 hover:underline focus:outline-none"
                    >
                        Disconnect
                    </button>
                ) : (
                    <button
                        onClick={ handleConnect }
                        className="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        Connect
                    </button>
                ) }
            </div>
        </div>
    );
};

export default ConnectionStatus;

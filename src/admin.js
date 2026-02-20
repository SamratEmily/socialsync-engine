import { createRoot } from 'react-dom/client';
import App from './Components/App';
import './styles/admin.css';

document.addEventListener( 'DOMContentLoaded', () => {
    const container = document.getElementById( 'socialsync-engine-app' );

    if ( container ) {
        const root = createRoot( container );
        root.render( <App /> );
    }
} );

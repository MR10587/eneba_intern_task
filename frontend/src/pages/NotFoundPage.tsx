import { Link } from 'react-router-dom'
import EmptyState from '../components/ui/EmptyState'

export default function NotFoundPage() {
    return (
        <EmptyState title="Page not found" message="That page does not exist in this storefront.">
            <Link className="btn btn--primary" to="/">
                Back to home
            </Link>
        </EmptyState>
    )
}

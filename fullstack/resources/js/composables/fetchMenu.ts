import axios from 'axios';

export interface MenuItem {
  id: number;
  name: string;
  description: string;
  price: number;
}

export interface MenuCategory {
  id: number;
  name: string;
  items: MenuItem[];
}

export default async function fetchMenu(): Promise<MenuCategory[]> {
  const response = await axios.get('/api/menu');
  return response.data as MenuCategory[];
}
